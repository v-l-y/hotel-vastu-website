# SEO Launch Checklist — Hotel Vastu Premium

## Technical indexing

- [ ] `https://hotelvastu.com/` returns HTTP 200.
- [ ] HTTPS is forced.
- [ ] `www` redirects to the non-www canonical host.
- [ ] `robots.txt` is publicly accessible.
- [ ] `sitemap.xml` is publicly accessible.
- [ ] Classic Room old URL returns 301 to the new URL.
- [ ] Club Room old URL returns 301 to the new URL.
- [ ] Premium Room old URL returns 301 to the new URL.
- [ ] Luxury Room remains `noindex` unless it becomes a current first-party room type.
- [ ] Deluxe Room remains `noindex` until confirmed.
- [ ] Suite Room remains `noindex` until confirmed.
- [ ] Privacy and Booking Information remain `noindex,follow`.
- [ ] 404 responses use the custom 404 page.

## Google Search Console

1. Verify the **Domain property** for `hotelvastu.com` using DNS.
2. Submit:
   `https://hotelvastu.com/sitemap.xml`
3. Use URL Inspection for:
   - homepage
   - rooms page
   - Classic Room
   - Club Room
   - Premium Room
   - contact page
   - Hotel near RPS More page
4. Request indexing after the production deployment is stable.
5. Watch Coverage/Pages reports for redirect, canonical and not-found errors.

## Structured data validation

Validate production URLs in Google's Rich Results Test / Schema validator:

- Homepage: Hotel + WebSite + FAQ data.
- Inner pages: BreadcrumbList.
- Classic/Club/Premium room pages: HotelRoom.

Do not add AggregateRating/Review schema just to mirror a changing Google rating.

## Google Business Profile consistency

Canonical GBP-matched data is in `BUSINESS-DATA.md`.

Keep consistent:

- Business name
- Address
- Phone
- Website URL

Website target:
`https://hotelvastu.com/`

After launch, update the Google Business Profile website field if needed.

## Images

Use original Hotel Vastu Premium images, not copied OTA thumbnails or generic stock photos.

Recommended minimum set:

- hotel exterior / entrance
- reception
- Classic Room
- Club Room
- Premium Room
- additional current first-party room views
- restaurant/dining
- parking / access

Use WebP or AVIF where practical. Give each file a descriptive filename and meaningful alt text.

## Content quality

- Keep location content useful, not keyword-stuffed.
- Do not create dozens of thin "hotel near X" pages.
- Add a location page only when it has genuine visitor value and accurate distance/context.
- Update room pages when real facilities or policies are confirmed.

## Local prominence

Operational tasks outside the codebase:

- Respond to genuine Google reviews.
- Add fresh original hotel photos to Google Business Profile.
- Keep opening/reception information accurate.
- Correct inconsistent OTA NAP data where possible.
- Seek legitimate local/travel citations and links.
- Never buy fake reviews or spam backlinks.

## Monitoring after launch

Check Search Console weekly during the first month for:

- indexing,
- crawl errors,
- duplicate canonicals,
- mobile usability,
- Core Web Vitals.

Also search the brand name and key local queries periodically to verify the official domain is being discovered.
