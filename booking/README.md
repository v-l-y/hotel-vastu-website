# Hotel Vastu Booking

Laravel 13 application for the separate Hotel Vastu booking, PMS and restaurant system.

## Deployment boundary

- `hotelvastu.com` — existing static public website
- `booking.hotelvastu.com` — this Laravel application

The static site remains independent. Its Book Now links should be switched only after this app is deployed and real hotel inventory/rates are configured.

## Implemented

- PHP 8.3+ / Laravel 13 / MySQL production configuration
- physical room inventory and room types
- maintenance room blocks
- room capacity validation
- rate plans, base rates and non-overlapping dated rates
- effective hotel/restaurant tax rules
- overlap-safe availability calculation
- 10-minute transaction-locked reservation holds
- guest details and one-time hold conversion
- immutable nightly reservation price snapshots
- booking number + opaque public confirmation token
- cancellation and no-show lifecycle
- manual verified payments: cash, UPI, card, bank transfer
- idempotent payment/refund service
- front desk check-in with physical room assignment
- guest stays and guest folios
- restaurant dine-in, room service and takeaway
- KOT creation and kitchen status flow
- served room-service posting to guest folio
- checkout balance enforcement
- invoice snapshot generation
- role-protected admin login and modules
- administrator setup for rooms, rates, tax, maintenance blocks and restaurant master data
- dashboard and basic operational reports
- GitHub Actions Booking CI using PHP 8.3 and SQLite test database

No room count, room number, price, tax, occupancy limit, payment credential or hotel policy is invented by seed data.

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

The command prompts securely for a password and requires at least 12 characters.

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

Before customer booking can proceed, configure real physical rooms, a rate plan, room pricing and applicable tax rules in Admin → Setup.

## Payments

The current application records **verified hotel-side payments** only: cash, UPI, card and bank transfer. It does not pretend an external online gateway payment succeeded. A real online gateway must be connected with its provider credentials, signed order/payment verification and webhook processing before `Book Now` can collect money online.

## Tests

```bash
composer test
```

CI runs Composer validation, dependency installation and the PHP test suite for every booking-system change.
