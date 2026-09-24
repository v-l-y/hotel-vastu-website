# Hotel Vastu Premium Website

Static, SEO-first website for **Hotel Vastu Premium, Danapur, Patna**.

## Source-of-truth policy

Only publicly verified or hotel-confirmed facts should be indexable.

Current verified baseline used by the site:
- Hotel Vastu Premium
- Google Business Profile address: near RPS Law College, RPS Nagar, Kaliket Nagar, Patna, Bihar 801503, India
- Current first-party room menu: Classic Room, Club Room, Premium Room
- Air-conditioned family accommodation
- Free Wi-Fi
- Restaurant / Asian breakfast
- Free private parking
- Room service
- 24-hour front desk
- Public hotel phone: +91 80020 07466

Do **not** invent phone numbers, WhatsApp numbers, emails, prices, geo coordinates, guest reviews, awards, founding dates or room categories.

## Booking System v1.0

The separate Laravel booking/PMS/restaurant application lives in `booking/`.
Its v1.0 product scope is frozen in `booking/docs/MASTER_BLUEPRINT.md`. Future v1.0 reviews verify that contract rather than expanding product scope.

## Structure

- `index.html`
- `rooms.html`
- `classic-room.html`
- `club-room.html`
- `premium-room.html`
- `luxury-room.html` — noindex legacy/unconfirmed
- `deluxe-room.html` — noindex until confirmed
- `suite-room.html` — noindex until confirmed
- `restaurant.html`
- `gallery.html`
- `about.html`
- `contact.html`
- `hotel-near-rps-more.html`
- `hotel-near-danapur-railway-station.html`
- `css/` shared design system
- `js/` lightweight site behavior
- `robots.txt`
- `sitemap.xml`
- `site.webmanifest`
- `404.html`

## Image paths

Current first-party room photography has been migrated from the live Hotel Vastu website, technically refined, resized and compressed to WebP:

- `images/hotel/hero.webp`
- `images/hotel/about.webp`
- `images/hotel/og-hotel-vastu.webp`
- `images/branding/hotel-vastu-logo.png`
- `images/branding/hotel-vastu-icon-192.png`
- `images/branding/hotel-vastu-icon-512.png`
- `images/rooms/classic-room.webp` + gallery images
- `images/rooms/club-room.webp` + gallery images
- `images/rooms/premium-room.webp` + gallery images

The migration uses the hotel's own current website assets, not OTA thumbnails or stock photography.

## Before production launch

1. Reconfirm the public phone before launch; WhatsApp and email are still unconfirmed.
2. Review the migrated/refined current-site photos. Restaurant uses genuine first-party hotel interior photography with a context label until a dedicated dining photo is confirmed; legacy unconfirmed room pages keep clearly labelled placeholders.
3. Confirm exact Google Maps URL and geo coordinates.
4. Reconfirm current room rates/policies before publishing fixed pricing; current room types are Classic, Club and Premium.
5. Confirm check-in/check-out policy directly with the hotel.
6. Keep the verified public phone as the canonical direct-booking path. The stay planner intentionally does not transmit personal details; add a receiving endpoint only after the hotel confirms email, WhatsApp or another dedicated channel.
7. Validate Hotel JSON-LD with Google Rich Results Test.
8. Submit `https://hotelvastu.com/sitemap.xml` in Google Search Console.
9. Ensure HTTPS and redirect all alternate hosts to `https://hotelvastu.com/`.
10. Keep Google Business Profile name/address/phone consistent with the website.

## Local run

Open `index.html` directly or serve the folder using any static HTTP server.

No framework, database, Bootstrap or jQuery is required. Source CSS stays split for maintenance; run `npm run build:css` after CSS edits to refresh the committed `css/site.css` production bundle used by the highest-traffic pages.

### Static QA

With Node.js 18+ installed, run:

```bash
npm run check
```

The static check validates internal links/assets, titles, canonical URLs, JSON-LD, sitemap/noindex separation, robots.txt, legacy redirects, branding, CSS-bundle freshness, truthful booking behavior, smooth scrolling, premium page structure and key business-data tokens. GitHub Actions also runs Browser QA in Chromium on desktop/mobile, Axe WCAG checks, booking-date interaction checks, gallery/mobile-nav checks and a Lighthouse quality gate.

## NAP source of truth

The connected local-business result matches Hotel Vastu Premium by name and public phone. The website now uses the Google Business Profile address documented in `BUSINESS-DATA.md`. Do not assume the verified phone is also a WhatsApp number.

## Apache / Hostinger deployment

The repository includes a root `.htaccess` that:
- redirects HTTP to HTTPS,
- redirects `www.hotelvastu.com` to the canonical `hotelvastu.com` host,
- serves `404.html` for missing pages,
- enables compression and browser caching when the corresponding Apache modules are available,
- adds basic security headers.

If the production host is not Apache-compatible, recreate the same redirects, caching and headers in that platform's configuration instead of relying on `.htaccess`.

## Business detail maintenance

Read `BUSINESS-DATA.md` before changing name, address, phone, room status or other public hotel facts.

## Launch documentation

- `BUSINESS-DATA.md` — canonical hotel business facts
- `DEPLOYMENT.md` — production/Hostinger deployment runbook
- `SEO-CHECKLIST.md` — Google Search Console and local SEO launch checklist


## Maintenance workflows

Image discovery, current-photo migration and final-polish workflows are manual-only. Normal pushes to `main` never let those maintenance jobs rewrite pages or assets automatically. The photo migration script resolves the current live Vite bundle and image hashes dynamically before downloading first-party assets.
