# Production Deployment Runbook — Hotel Vastu Premium

Target canonical domain: **https://hotelvastu.com/**

## 1. Before replacing the current site

- Take a full backup of the current `public_html` / website root.
- Export or save any current server configuration that is not stored in this repository.
- Confirm SSL is active for both `hotelvastu.com` and `www.hotelvastu.com`.
- Keep the existing site available until the new files are fully uploaded.

## 2. Files to upload

Upload the repository contents to the web root, including hidden files:

- HTML pages
- `css/`
- `js/`
- `images/` after original hotel photos are added
- `favicon.svg`
- `site.webmanifest`
- `robots.txt`
- `sitemap.xml`
- `404.html`
- `.htaccess`

Do not expose repository-only Markdown documents if your deployment process lets you exclude them.

## 3. Critical redirect preservation

The existing website has this known public URL:

`/room/classic-room`

The included `.htaccess` permanently redirects it to:

`/classic-room.html`

Verify after deployment:

`https://hotelvastu.com/room/classic-room`

must return a **301** redirect to:

`https://hotelvastu.com/classic-room.html`

Do not remove this redirect after launch.

## 4. Canonical host

The included Apache config redirects:

- HTTP → HTTPS
- `www.hotelvastu.com` → `hotelvastu.com`

Expected canonical homepage:

`https://hotelvastu.com/`

If the production server does not use Apache, recreate the same redirects in the host configuration.

## 5. Required pre-launch hotel inputs

Before calling the site fully production-ready:

- Add original hotel exterior/hero image.
- Add original Classic Room image.
- Add original Luxury Room image if that room type remains published.
- Add original restaurant image if restaurant imagery is available.
- Confirm WhatsApp before adding any WhatsApp CTA.
- Confirm email before publishing it.
- Confirm check-in/check-out and cancellation policies before publishing them.
- Keep Deluxe/Suite noindex until the hotel confirms those room types.

See `BUSINESS-DATA.md` for the current factual source of truth.

## 6. Smoke checks after upload

Open and verify:

- `/`
- `/rooms.html`
- `/classic-room.html`
- `/luxury-room.html`
- `/restaurant.html`
- `/gallery.html`
- `/about.html`
- `/contact.html`
- `/hotel-near-rps-more.html`
- `/hotel-near-danapur-railway-station.html`
- `/robots.txt`
- `/sitemap.xml`
- a nonexistent URL to confirm `404.html`

Also verify:

- Call links open the dialer with +91 80020 07466.
- Directions open the exact Google Business Profile place.
- Mobile navigation opens/closes correctly.
- Booking dates cannot be set in the past.
- No broken image requests remain once real photos are installed.

## 7. Do not publish stale placeholders

Production pages must not claim:

- unconfirmed rates,
- unconfirmed room types,
- fake guest reviews,
- fake star classification,
- fake awards,
- an unverified WhatsApp/email,
- unconfirmed check-in/check-out rules.

## 8. Rollback

If a major issue occurs:

1. Restore the previous website backup.
2. Keep the domain/SSL configuration unchanged.
3. Diagnose the static deployment offline.
4. Redeploy only after the smoke checks pass.
