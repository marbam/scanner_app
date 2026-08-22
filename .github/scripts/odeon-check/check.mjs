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
  const page = await browser.newPage({
    userAgent:
      'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
  });

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
