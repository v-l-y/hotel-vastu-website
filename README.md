# Hotel Vastu Premium Website

Static, SEO-first website for **Hotel Vastu Premium, Danapur, Patna**.

## Source-of-truth policy

Only publicly verified or hotel-confirmed facts should be indexable.

Current verified baseline used by the site:
- Hotel Vastu Premium
- RPS More Vijay Complex, RPS Kali Mandir Road, beside Vastu Estates Colony, opposite Hanuman Mandir, Danapur, Patna, Bihar 801503
- Luxury room listing
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
- `luxury-room.html`
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

The UI has graceful visual fallbacks until original photos are supplied.

Expected files:
- `images/hotel/hero.webp`
- `images/hotel/exterior.webp`
- `images/rooms/luxury-room.webp`
- `images/restaurant/restaurant.webp`

Use original hotel photography, preferably WebP/AVIF, with meaningful filenames and proper dimensions.

## Before production launch

1. Reconfirm the public phone before launch; WhatsApp and email are still unconfirmed.
2. Add original hotel photos.
3. Confirm exact Google Maps URL and geo coordinates.
4. Confirm all room categories, occupancy and current rates.
5. Confirm check-in/check-out policy directly with the hotel.
6. Enable the booking form with a real endpoint or confirmed WhatsApp number.
7. Validate Hotel JSON-LD with Google Rich Results Test.
8. Submit `https://hotelvastu.com/sitemap.xml` in Google Search Console.
9. Ensure HTTPS and redirect all alternate hosts to `https://hotelvastu.com/`.
10. Keep Google Business Profile name/address/phone consistent with the website.

## Local run

Open `index.html` directly or serve the folder using any static HTTP server.

No framework, database, Bootstrap, jQuery or build step is required.

## NAP verification note

Public listings currently disagree on the formatted street address. Keep the existing detailed address provisional until the hotel confirms the exact Google Business Profile NAP. Do not assume the verified phone is also a WhatsApp number.
