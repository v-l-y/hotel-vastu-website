# Production Deployment Runbook — Hotel Vastu Premium

Target canonical domain: **https://hotelvastu.com/**

## 1. Before replacing the current site

- Take a full backup of the current `public_html` / website root.
- Export or save any current server configuration that is not stored in this repository.
- Confirm SSL is active for both `hotelvastu.com` and `www.hotelvastu.com`.
- Keep the existing site available until the new files are fully uploaded.

## 2. Files to upload

Upload the deployable contents of the repository's `website/` directory to the web root, including hidden files:

- HTML pages
- `css/`
- `js/`
- `images/` including migrated/refined first-party photos and clearly labelled legacy placeholder visuals
- `images/branding/hotel-vastu-icon-192.png`
- `images/branding/hotel-vastu-icon-512.png`
- `site.webmanifest`
- `robots.txt`
- `sitemap.xml`
- `404.html`
- `.htaccess`

Do not expose repository-only Markdown documents if your deployment process lets you exclude them.

## 3. Critical redirect preservation

The existing website has these current public room URLs:

- `/room/classic-room`
- `/room/club-room`
- `/room/premium-room`

The included `.htaccess` permanently redirects them to:

- `/classic-room.html`
- `/club-room.html`
- `/premium-room.html`

Verify after deployment that all three old room URLs return **301** redirects to their matching new static pages. Do not remove these redirects after launch.

## 4. Canonical host

The included Apache config redirects:

- HTTP → HTTPS
- `www.hotelvastu.com` → `hotelvastu.com`

Expected canonical homepage:

`https://hotelvastu.com/`

If the production server does not use Apache, recreate the same redirects in the host configuration. Also reproduce the repository security headers, including Content-Security-Policy, Strict-Transport-Security, X-Content-Type-Options, X-Frame-Options, Referrer-Policy and Permissions-Policy.

## 5. Required pre-launch hotel inputs

Before calling the site fully production-ready:

- Review the migrated/refined current-site hero and room photography.
- Confirm Classic, Club and Premium remain the active room types.
- Keep Luxury noindex unless the hotel explicitly restores that room type.
- Add a dedicated verified first-party restaurant photo when available; until then the page uses genuine hotel interior photography with a clear context label.
- Keep the verified public phone as the direct-booking confirmation path.
- Confirm WhatsApp before adding any WhatsApp CTA.
- Confirm email before publishing it.
- Do not add a transmitting enquiry endpoint until the hotel confirms the receiving channel.
- Confirm check-in/check-out and cancellation policies before publishing them.
- Keep Deluxe/Suite noindex until the hotel confirms those room types.

See `BUSINESS-DATA.md` for the current factual source of truth.

## 6. Smoke checks after upload

Open and verify:

- `/`
- `/rooms.html`
- `/classic-room.html`
- `/club-room.html`
- `/premium-room.html`
- `/facilities.html`
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

- GitHub Static Site QA and Browser QA are both green; Browser QA includes Axe accessibility checks and the Lighthouse quality gate.

- Call links open the dialer with +91 80020 07466.
- Directions open the exact Google Business Profile place.
- Mobile navigation opens/closes correctly.
- Booking dates cannot be set in the past.
- No broken image requests remain; the restaurant contextual photo is clearly labelled and legacy unconfirmed-room placeholders remain clearly marked.

## 7. Do not publish stale placeholders

Production pages must not claim:

- unconfirmed rates,
- unconfirmed room types,
- fake guest reviews,
- fake star classification,
- fake awards,
- an unverified WhatsApp/email,
- unconfirmed check-in/check-out rules.

## 8. Cache behavior

The repository uses short cache windows because CSS/JS/image filenames are stable rather than content-hashed:

- HTML: revalidate on every visit.
- CSS/JavaScript/manifest: approximately 1 hour.
- PNG/SVG/WebP/AVIF images: approximately 7 days.

After a production upload, hard-refresh once and verify the current header/logo/styles before considering deployment complete.

## 9. Rollback

If a major issue occurs:

1. Restore the previous website backup.
2. Keep the domain/SSL configuration unchanged.
3. Diagnose the static deployment offline.
4. Redeploy only after the smoke checks pass.
