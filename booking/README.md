# Hotel Vastu Booking

Laravel 13 application for the separate Hotel Vastu booking, PMS, billing and restaurant system.

## Deployment boundary

- `hotelvastu.com` — static public/SEO website
- `booking.hotelvastu.com` — this Laravel booking/PMS application

Public booking CTAs and the homepage stay form now point to `https://booking.hotelvastu.com/`. The public form sends only non-PII stay-prefill values (dates, adults and room preference); guest name/contact details are collected inside the booking application.

## Implemented

- PHP 8.3+ / Laravel 13 / MySQL production configuration
- physical rooms, room types and housekeeping states
- maintenance room blocks
- room capacity validation
- rate plans, base rates and non-overlapping dated rates
- per-night effective tax calculation and immutable tax/rate snapshots
- overlap-safe availability and 10-minute transaction-locked holds
- guest details, booking number and opaque public confirmation token
- pre-arrival reservation editing with availability recheck and repricing
- cancellation and no-show lifecycle
- verified manual payments: cash, UPI, card and bank transfer
- overpayment prevention and idempotent payment/refund accounting
- optional verified Razorpay online checkout, signature verification and captured-payment verification
- signed Razorpay webhooks for captured payments and refund reconciliation
- provider-backed online refunds with pending/processed/failed lifecycle
- front desk check-in with physical-room assignment
- room transfers, stay extensions and housekeeping workflow
- guest stays and guest folios
- pending room-service checkout protection
- checkout settlement enforcement
- immutable invoice snapshots and post-invoice credit notes
- restaurant dine-in, room service and takeaway
- multi-item POS orders, table occupancy and KOT workflow
- role-protected admin modules
- administrator user management, password reset and audit trail
- operational payments/refunds console
- occupancy, ADR, room revenue, tax, payment split and restaurant reports
- SQLite feature CI plus MySQL 8 production-contract CI
- MySQL row-lock contract coverage
- static-site and browser booking-redirect regression guards

No room count, room number, price, tax, capacity, menu item, table count or hotel policy is invented by seed data.

## Admin roles

- `administrator`
- `front_desk`
- `restaurant`
- `kitchen`
- `accounts`

Create the first administrator after migration:

```bash
php artisan admin:create admin@example.com --name="Hotel Administrator" --role=administrator
```

The command securely prompts for a password and requires at least 12 characters.

## Local setup

```bash
cd booking
composer install
copy .env.example .env
php artisan key:generate
```

Create the MySQL database configured in `.env`, then:

```bash
php artisan migrate
php artisan db:seed
php artisan admin:create admin@example.com --name="Hotel Administrator"
php artisan serve
```

Open:

- customer booking: `http://127.0.0.1:8000`
- admin: `http://127.0.0.1:8000/admin/login`

Configure real rooms, rates, tax rules and restaurant master data before accepting bookings.

## Razorpay activation

Online payment remains hidden unless credentials are configured:

```env
RAZORPAY_KEY_ID=
RAZORPAY_KEY_SECRET=
RAZORPAY_WEBHOOK_SECRET=
```

For production:

1. Set production Razorpay keys only in the server environment.
2. Configure the webhook URL as `https://booking.hotelvastu.com/payments/razorpay/webhook`.
3. Subscribe to captured-payment and refund lifecycle events used by the application.
4. Keep the webhook secret different from the API key secret.
5. Confirm payment capture behavior in the Razorpay account before live traffic.
6. Run a real low-value live payment/refund reconciliation test before opening public checkout.

Never commit live gateway credentials.

## Production deployment checklist

1. Point `booking.hotelvastu.com` DNS to the Laravel hosting environment and set the web document root to `booking/public`.
2. Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://booking.hotelvastu.com`.
3. Use production MySQL credentials and take a backup before migrations.
4. Run `php artisan migrate --force`.
5. Run `php artisan optimize` after the production environment is configured.
6. Ensure `storage/` and `bootstrap/cache/` are writable by PHP.
7. Create the first administrator securely.
8. Enter real rooms, rates, taxes, tables and menu items in Admin.
9. Verify `/health`, customer booking, admin login, check-in/out, POS/KOT and invoice flow.
10. If Razorpay is enabled, verify signed checkout, webhook delivery and refund reconciliation.

## Tests

```bash
composer test
```

GitHub Booking CI treats warnings as failures and runs the PHP suite against both SQLite and MySQL 8.
