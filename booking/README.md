# Hotel Vastu Booking

Laravel 13 application for the separate Hotel Vastu booking, PMS, billing and restaurant system.

## v1.0 scope freeze

**Booking System v1.0 is scope-frozen.** The canonical contract is:

- [MASTER BLUEPRINT v1.0 — BOOKING SYSTEM SCOPE FREEZE](docs/MASTER_BLUEPRINT.md)

For v1.0, that document is the single source of truth from public booking search through checkout, invoice, restaurant/POS/KOT, RBAC, reporting and release gates.

A future v1.0 review may identify a concrete bug, regression, security/authorization issue, data-integrity/concurrency defect, missing implementation against the blueprint, or deployment/configuration issue. It must **not** introduce a new product capability or new business rule and then treat that enhancement as unfinished v1.0 work.

New product scope requires an explicitly approved later version such as v1.1 or v2.0.

## Deployment boundary

- `hotelvastu.com` — static public/SEO website
- `booking.hotelvastu.com` — this Laravel booking/PMS application

Public booking CTAs and the homepage stay form now point to `https://booking.hotelvastu.com/`. The public form sends only non-PII stay-prefill values (dates, adults and room preference); guest name/contact details are collected inside the booking application.

## Implemented

- PHP 8.3+ / Laravel 13 / MySQL production configuration
- physical rooms, room types and housekeeping states
- inventory-safe maintenance room blocks with historical closure tracking
- room capacity validation
- rate plans, base rates and non-overlapping dated rates
- per-night effective tax calculation and immutable tax/rate snapshots
- peak-night overlap-safe availability and 10-minute transaction-locked holds
- guest details, booking number and opaque public confirmation token
- pre-arrival reservation editing with availability recheck and repricing
- cancellation and no-show lifecycle
- verified manual payments: cash, UPI, card and bank transfer
- manual overpayment prevention, explicit provider-capture overpayment state and idempotent payment/refund accounting
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
- role-protected admin modules with restaurant/kitchen financial and operational boundaries
- administrator user management, password reset and audit trail
- operational payments/refunds console
- date-safe occupancy, ADR, room revenue, tax, payment split and served-at restaurant reports
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

`composer.lock` is committed and is a mandatory GitHub/local-CI release gate so production and test installs resolve the same dependency set.

Quick application test:

```bash
composer test
```

### Full local GitHub-CI parity on Windows

The repository includes a local runner that mirrors the Booking CI gates: v1.0 scope-freeze verification, Composer validation/install, SQLite feature suite, route/view cache build, and the full MySQL production-contract suite.

From `booking/`:

```bat
scripts\local-ci.cmd
```

The default local MySQL test connection is:

- host: `127.0.0.1`
- port: `3306`
- database: `hotel_vastu_booking_ci`
- username: `root`
- password: empty

For a different local MySQL password or port:

```bat
scripts\local-ci.cmd -MySqlPassword "your-password" -MySqlPort 3306
```

Or from PowerShell:

```powershell
.\scripts\local-ci.ps1 -MySqlUsername root -MySqlPassword ""
```

Safety guarantees:

- the runner refuses a MySQL database name unless it ends in `_ci` or `_test`;
- it never uses the normal `hotel_vastu_booking` database by default;
- the existing local `.env` is backed up before the run and restored in `finally`, including failed runs;
- `.env.example` is used temporarily so route/view cache checks match GitHub CI;
- required PHP 8.3 extensions are checked before tests start.

GitHub Booking CI treats warnings as failures and runs the PHP suite against both SQLite and MySQL 8. The local runner executes the same application gates against the locally installed MySQL-compatible server. For engine-identical verification, use MySQL 8.4 locally.
