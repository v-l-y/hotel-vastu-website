# Hotel Vastu Booking

Laravel 13 application for the separate Hotel Vastu booking/PMS system.

## Boundary

- `hotelvastu.com`: existing static public website
- `booking.hotelvastu.com`: this Laravel application

The static site's booking links should be switched only after this app is deployed and real room inventory has been configured.

## Phase 1 implemented

- Laravel 13 / PHP 8.3+
- MySQL configuration
- room types and physical rooms
- maintenance room blocks
- rate-plan and dated-rate schema
- reservations and reservation room lines
- expiring reservation holds
- overlap-based availability calculation
- transaction + row lock for hold creation
- public availability screen
- 10-minute inventory hold
- feature test
- only confirmed room category names seeded: Classic, Club, Premium

No room count, room number, rate, occupancy limit, tax, payment setting or hotel policy is invented.

## Local setup

```bash
cd booking
composer install
copy .env.example .env
php artisan key:generate
```

Create the MySQL database from `.env`, then run:

```bash
php artisan migrate
php artisan db:seed
php artisan serve
```

Add the hotel's real physical rooms before testing availability.

## Tests

```bash
composer test
```

## Next

Guest/reservation confirmation, pricing, payments, front desk/stays, folios/invoices, restaurant POS/KOT, admin auth and reports.
