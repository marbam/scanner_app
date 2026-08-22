# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

A single-user Laravel app that watches for things going on sale/becoming available (flight fares, ticket on-sale dates, festival tickets) and pushes a Pushover notification the moment one does. Built from the Laravel Livewire + Flux starter kit (Fortify auth). There is no public registration — this app is for one person only, created via an artisan command.

Local dev runs on Laravel Herd at `http://scanner_app.test`. Production is a single DigitalOcean droplet at `https://scanner.marbam.uk`, behind Cloudflare (proxied — see "Production" below).

## Commands

```bash
# Install and set up from scratch (copies .env, generates key, migrates, builds assets)
composer setup

# Local dev server + queue listener + Vite, all at once
composer dev

# Format code (Laravel Pint)
composer lint
composer lint:check          # check only, no changes (what CI runs)

# Static analysis (PHPStan/Larastan, level 7, on app/bootstrap/config/database/routes)
composer types:check

# Full test suite (config:clear, lint:check, types:check, then Pest)
composer test
composer ci:check            # same as `test`, used by GitHub Actions

# Run a single test file or filter
php artisan test tests/Feature/CheckRyanairAvailabilityTest.php
php artisan test --filter="matches the specific watched day"

# Create/update the one owner user from OWNER_NAME/OWNER_EMAIL/OWNER_PASSWORD in .env
php artisan app:create-owner-user
```

PHPStan needs more than the default 128M memory limit locally on Windows/Herd — run with `vendor/bin/phpstan analyse --memory-limit=1G` if `composer types:check` OOMs.

## Architecture

### Domain model: Interest → InterestCheck → Alert

- **`Interest`** (`app/Models/Interest.php`) is a single thing being watched — a flight route/date, a ticket on-sale, etc. Key columns: `provider` (a string key like `ryanair` that selects which job/logic handles it), `provider_params` (JSON, shape depends on the provider — e.g. Ryanair's is `{origin, destination, month, day}`), `status` (`watching` / `released` / `error`), `enabled` (the user's kill switch — checkers must respect this), `last_response_hash` (used to skip re-processing an unchanged response).
- **`InterestCheck`** logs every single check attempt against an interest, including the full raw JSON `response_body` — this is deliberate (see the project brief in git history) so responses can be reviewed for debugging, not just the derived outcome.
- **`Alert`** is created only when an interest's status flips to `released` — this is what the "Scans" dashboard surfaces as "something went live."

### Provider/checker pattern

Each `provider` value corresponds to one Job class that knows how to check that source. Three run on the droplet's own scheduler: `ryanair` (`app/Jobs/CheckRyanairAvailability.php`), `wells_comedy_festival` (`app/Jobs/CheckComedyFestivalOnSale.php`), and `showcase_cinemas` (`app/Jobs/CheckShowcaseCinemaOnSale.php`). The scheduler in `routes/console.php` has one `Schedule::call` block per provider, each querying `Interest::where('provider', <key>)->where('enabled', true)->where('status', '!=', 'released')` and dispatching the matching job. `ryanair` and `wells_comedy_festival` run once daily at 9am UK time (`dailyAt('09:00')->timezone('Europe/London')`, so it stays 9am local across the BST/GMT switch even though the app timezone is UTC); `showcase_cinemas` runs three times a day (`cron('0 9,13,17 * * *')->timezone('Europe/London')`, same DST-safe timezone handling — 9am/1pm/5pm local, not UTC). Adding a new checker type that can be reached with a plain server-side HTTP request means: a new Job class following this same log-every-attempt/hash-diff/notify-on-release shape, a new `provider` value, and a new scheduler entry.

`CheckRyanairAvailability` matches fares by the specific `day` in `provider_params`, not just "does the month have any fares at all" — Ryanair's FareFinder endpoint returns per-day fare data for the whole queried month, and the route doesn't fly every day, so month-level matching produces false positives. Always match on the specific day being watched.

`CheckComedyFestivalOnSale` is a plain HTML scraper, not a JSON API call — Wells Comedy Festival (`wellscomfest.com`, Squarespace) server-renders its `/whats-on-by-day` page, so a plain `Http::get` (no JS execution needed) sees the real content. `provider_params` is `{url, not_on_sale_text}`: between festivals the page shows a fixed message (e.g. "The 2026 festival is now over"); release is detected as that `not_on_sale_text` string disappearing from the response body once the next festival's line-up/booking goes live. Because the response isn't JSON, `response_body` is stored as `['html' => $response->body()]` to fit the `interest_checks.response_body` JSON column and the model's array cast.

`CheckShowcaseCinemaOnSale` hits Showcase Cinemas' own (undocumented but unauthenticated) frontend API — `showcasecinemas.co.uk/api/gatsby-source-boxofficeapi/participatingTheaters?movieIds=<id>` — which returns the list of theatre codes currently selling tickets for that film. `provider_params` is `{movie_id, theater_code}`; release is detected by the specific `theater_code` (e.g. `X06JH` for Showcase Bristol Avonmeads) appearing in that list, not just "is it on sale anywhere" — same reasoning as the Ryanair day-matching, since different Showcase venues go on sale on different days.

#### Odeon: webhook-driven provider (Cloudflare blocks server-side requests)

Odeon's site sits behind Cloudflare bot management (`cdn-cgi/challenge-platform`) and returns a 403 to any plain `Http::get`/`curl`-style request — confirmed against both the page HTML and its `/api/omnia/v1/...` JSON endpoints. A real browser is required to get past the JS challenge, which isn't something to run on this app's 1GB droplet. Instead, the `odeon` provider is checked externally:

- `.github/workflows/odeon-check.yml` runs on a GitHub Actions schedule — `cron: '0 9,13,17 * * *'`, three times a day; GitHub Actions cron is UTC-only with no timezone support, so this drifts an hour against UK local time during BST, which is fine since these are rough checks, not the DST-safe precision the droplet's own Laravel scheduler gives the other providers (`workflow_dispatch` also available for manual runs) — and drives `.github/scripts/odeon-check/check.mjs`, a small Playwright script that loads the target Odeon cinema page (currently `odeon.co.uk/cinemas/bristol/`, i.e. ODEON Luxe Bristol at Cabot Circus — the film's own `/films/<slug>/<id>/` page also exists but only the per-cinema "now showing" listing tells you whether *this* cinema has it on sale), and checks whether the watched film's title appears in that page's text — the same page only lists films currently bookable there, so title-presence is the on-sale signal (mirrors the Comedy Festival checker's text-presence approach).
- The script POSTs `{interest_id, on_sale, snapshot}` to `POST /api/cinema-checks` (`routes/api.php`, `app/Http/Controllers/CinemaCheckWebhookController.php`), authenticated by a shared secret compared with `hash_equals` in `X-Webhook-Token` (`app/Http/Middleware/VerifyCinemaWebhookToken.php`, config `services.cinema_webhook.token` / env `CINEMA_WEBHOOK_TOKEN`, mirrored as a GitHub Actions secret of the same name). The controller does the same hash-diff/log/notify-on-release work the in-process Jobs do, so this provider still shows up in `InterestCheck` history and the Scans dashboard like every other one.
- Needed secrets/vars on the GitHub side: repo secret `CINEMA_WEBHOOK_TOKEN` (same value as the app's env var, shared across all films) and one repo variable per watched film — e.g. `ODEON_AVENGERS_DOOMSDAY_INTEREST_ID`, `ODEON_AVENGERS_ENDGAME_INTEREST_ID` — holding the `interests.id` row to report against. Each film is its own job in `odeon-check.yml` (not a matrix), since GitHub Actions' `vars`/`secrets` contexts don't support dynamic key lookup from matrix values cleanly. Extending this to another film means: a new `Interest` row, a new job block in `odeon-check.yml` (copy an existing one, change the title/env), and a matching repo variable — same cinema page (`odeon.co.uk/cinemas/bristol/`) is reused since it lists whatever's currently on sale there, no per-film URL needed.
- This is the only provider whose "Job" doesn't live in `app/Jobs` — there's deliberately no scheduled Laravel Job for `odeon`; the scheduler blocks in `routes/console.php` only cover providers reachable by plain server-side HTTP.

### Notifications: Pushover on-demand routing gotcha

`Notification::route('pushover', ...)` must be passed a `NotificationChannels\Pushover\PushoverReceiver` instance (`PushoverReceiver::withUserKey($key)->withApplicationToken($token)`), **not** a raw array. Passing an array compiles fine and passes tests under `Notification::fake()`, but crashes in production the moment it hits a real send (`Call to a member function toArray() on array` inside the package's `PushoverChannel`). Because `PushoverReceiver` construction happens in application code (not inside the fakeable channel), tests must seed `services.pushover.user_key`/`token` config with dummy values *before* calling code that constructs a receiver, even under `Notification::fake()` — see `beforeEach()` in `tests/Feature/CheckRyanairAvailabilityTest.php`.

### Livewire 4 view path convention

A component named `Index` inside a namespaced folder — e.g. `App\Livewire\Interests\Index` — resolves to `resources/views/livewire/interests.blade.php`, **not** `resources/views/livewire/interests/index.blade.php` as Livewire 3 conventions would suggest. This differs from `Settings\Profile` → `livewire/settings/profile.blade.php`, which follows the pattern you'd expect.

### Single-user auth

Registration is fully removed, not just hidden: `Features::registration()` is absent from `config/fortify.php`'s feature list, so Fortify never registers the `/register` routes at all, and `app/Actions/Fortify/CreateNewUser.php` doesn't exist. The only way to create the account is `php artisan app:create-owner-user`, which reads `OWNER_NAME`/`OWNER_EMAIL`/`OWNER_PASSWORD` from `.env` (via `config/owner.php`) and does an idempotent `updateOrCreate` — safe to re-run after changing the password in `.env`. `User::$fillable` is set via a `#[Fillable(...)]` attribute restricted to `['name', 'email', 'password']`, so anything else (like `email_verified_at`) needs `forceFill()`.

### Bot/scraper hardening

`public/robots.txt` disallows everything. `/` redirects guests to `/login` and authenticated users to `/dashboard` — there's no public page to crawl or index. Login, 2FA, passkeys, and `/pushover-test` are all rate-limited (see `app/Providers/FortifyServiceProvider.php` and the route definitions in `routes/web.php`).

## Production

Deployed manually (no CI/CD pipeline) to a DigitalOcean droplet (`167.71.140.85`, Ubuntu 24.04, 1GB RAM + 1GB swap) at `/var/www/scanner_app`, owned by `www-data`. Deploy is `git pull` + `composer install --no-dev` + `npm run build` as needed, run as `sudo -u www-data`.

- **DNS/WAF**: `scanner.marbam.uk` is proxied through Cloudflare (orange cloud). SSL/TLS mode is "Full". The DigitalOcean firewall only allows inbound 80/443 from Cloudflare's published IP ranges — direct-IP access bypassing Cloudflare is blocked. nginx's `realip` module (`/etc/nginx/conf.d/cloudflare-realip.conf`) rewrites `$remote_addr` from Cloudflare's `CF-Connecting-IP` header so Laravel's rate limiters and logs see real visitor IPs, not Cloudflare's edge IP — this is safe only because the firewall already restricts who can reach nginx at all.
- **TLS**: Let's Encrypt via certbot, auto-renewing.
- **Queue**: `scanner-queue` systemd service runs `artisan queue:work`, `Restart=always`, recycles hourly (`--max-time=3600`).
- **Scheduler**: `www-data`'s crontab runs `php artisan schedule:run` every minute — this is what actually triggers the `routes/console.php` schedule (daily 9am UK-time checks etc.).
- **Known deferred item**: the droplet's kernel is intentionally held back from the latest security update (`apt-get upgrade` pulled in a kernel bump that hung the 1GB/1-vCPU box mid-`initramfs` rebuild once already) — do a deliberate, isolated `apt-get dist-upgrade` for kernel updates rather than a blanket upgrade, and not while anything else is mid-deploy.
- Root SSH login is intentionally left enabled (single-operator box, key-only auth already enforced) — not an oversight.
