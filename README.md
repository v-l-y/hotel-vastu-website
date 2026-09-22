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
- `images/hotel/og-hotel-vastu.webp`
- `images/rooms/classic-room.webp` + gallery images
- `images/rooms/club-room.webp` + gallery images
- `images/rooms/premium-room.webp` + gallery images

The migration uses the hotel's own current website assets, not OTA thumbnails or stock photography.

## Before production launch

1. Reconfirm the public phone before launch; WhatsApp and email are still unconfirmed.
2. Review the migrated/refined current-site photos and add any additional hotel exterior/restaurant/parking photos if desired.
3. Confirm exact Google Maps URL and geo coordinates.
4. Reconfirm current room rates/policies before publishing fixed pricing; current room types are Classic, Club and Premium.
5. Confirm check-in/check-out policy directly with the hotel.
6. Enable the booking form with a real endpoint or confirmed WhatsApp number.
7. Validate Hotel JSON-LD with Google Rich Results Test.
8. Submit `https://hotelvastu.com/sitemap.xml` in Google Search Console.
9. Ensure HTTPS and redirect all alternate hosts to `https://hotelvastu.com/`.
10. Keep Google Business Profile name/address/phone consistent with the website.

## Local run

Open `index.html` directly or serve the folder using any static HTTP server.

No framework, database, Bootstrap, jQuery or build step is required.\n\n### Static QA\n\nWith Node.js 18+ installed, run:\n\n```bash\nnpm run check\n```\n\nThe check validates internal links/assets, titles, canonical URLs, JSON-LD, sitemap/noindex separation, robots.txt, the legacy Classic Room redirect and key business-data tokens. The same check also runs in GitHub Actions on `main`.

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
