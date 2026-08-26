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

// --disable-blink-features=AutomationControlled hides the most obvious
// CDP-driven-browser tell (it's what flips navigator.webdriver on by
// default); Cloudflare's bot management checks for exactly that signal
// among others.
const browser = await chromium.launch({
  args: ['--disable-blink-features=AutomationControlled'],
});
let snapshot;

try {
  // No custom userAgent override here on purpose: a hardcoded UA string
  // drifts out of sync with the real Chromium version's own Sec-CH-UA
  // client-hint headers as the `playwright` dependency gets bumped, and
  // that UA/client-hints mismatch is exactly what got every check silently
  // Cloudflare-blocked (see the `blocked` check below, which is what
  // caught it).
  const context = await browser.newContext({
    locale: 'en-GB',
    timezoneId: 'Europe/London',
    viewport: { width: 1366, height: 768 },
  });

  // Playwright's automated context still leaves a few JS-visible tells
  // beyond navigator.webdriver (empty plugins/mimeTypes lists, no
  // window.chrome on Chromium, permissions.query always resolving
  // "denied") that bot-detection scripts commonly check for. Patch them
  // before any page script runs.
  await context.addInitScript(() => {
    Object.defineProperty(navigator, 'webdriver', { get: () => undefined });
    Object.defineProperty(navigator, 'plugins', { get: () => [1, 2, 3, 4, 5] });
    Object.defineProperty(navigator, 'languages', { get: () => ['en-GB', 'en'] });
    window.chrome = { runtime: {} };
    const originalQuery = window.navigator.permissions.query;
    window.navigator.permissions.query = (parameters) =>
      parameters.name === 'notifications'
        ? Promise.resolve({ state: Notification.permission })
        : originalQuery(parameters);
  });

  const page = await context.newPage();

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

// A Cloudflare block/challenge page reads as "the film isn't listed" to a
// plain title-presence check, which would otherwise fail the run's
// *purpose* (detecting a release) without failing the *run* — so it never
// surfaces anywhere. Detect it explicitly and blow up instead of silently
// reporting on_sale=false. Covers both the hard block page ("Ray ID" /
// "has been blocked") and the managed-challenge interstitial ("Performing
// security verification") — Cloudflare's managed challenge is built to
// detect and withhold clearance from headless/automated browsers, so this
// script currently can't get past it even with a patched fingerprint.
if (
  snapshot.includes('Cloudflare Ray ID')
  || snapshot.includes('has been blocked')
  || snapshot.includes('Performing security verification')
) {
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
