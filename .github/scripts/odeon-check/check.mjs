import { chromium } from 'playwright';

const cinemaUrl = requireEnv('ODEON_CINEMA_URL');
const filmTitle = requireEnv('ODEON_FILM_TITLE');
const interestId = requireEnv('INTEREST_ID');
const webhookUrl = requireEnv('WEBHOOK_URL');
const webhookToken = requireEnv('WEBHOOK_TOKEN');

function requireEnv(name) {
  const value = process.env[name];
  if (!value) {
    throw new Error(`Missing required env var: ${name}`);
  }
  return value;
}

const browser = await chromium.launch();
let snapshot;

try {
  // No custom userAgent override here on purpose: a hardcoded UA string
  // drifts out of sync with the real Chromium version's own Sec-CH-UA
  // client-hint headers as the `playwright` dependency gets bumped, and
  // that UA/client-hints mismatch is exactly what got every check silently
  // Cloudflare-blocked (see the `blocked` check below, which is what
  // caught it).
  const page = await browser.newPage();

  await page.goto(cinemaUrl, { waitUntil: 'networkidle' });

  // Cookie banner blocks nothing we read here, but dismiss it anyway in
  // case a future layout change puts it in front of the film listing.
  const declineCookies = page.getByRole('button', { name: /no thanks/i });
  if (await declineCookies.isVisible().catch(() => false)) {
    await declineCookies.click();
  }

  snapshot = (await page.locator('body').innerText()).trim();
} finally {
  await browser.close();
}

// A Cloudflare block page reads as "the film isn't listed" to a plain
// title-presence check, which would otherwise fail the run's *purpose*
// (detecting a release) without failing the *run* — so it never surfaces
// anywhere. Detect it explicitly and blow up instead of silently reporting
// on_sale=false.
if (snapshot.includes('Cloudflare Ray ID') || snapshot.includes('has been blocked')) {
  throw new Error(`Blocked by Cloudflare while loading ${cinemaUrl} — snapshot: ${snapshot.slice(0, 500)}`);
}

const onSale = snapshot.includes(filmTitle);

const response = await fetch(webhookUrl, {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-Webhook-Token': webhookToken,
  },
  body: JSON.stringify({
    interest_id: Number(interestId),
    on_sale: onSale,
    snapshot,
  }),
});

if (!response.ok) {
  throw new Error(`Webhook returned ${response.status}: ${await response.text()}`);
}

console.log(`Reported on_sale=${onSale} for interest ${interestId}`);
