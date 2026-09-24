# Hotel Vastu Booking

Laravel 13 application for the separate Hotel Vastu booking/PMS system.

## Boundary

- `hotelvastu.com`: existing static public website
- `booking.hotelvastu.com`: this Laravel application

The static site's booking links should be switched only after this app is deployed and real room inventory has been configured.

## Implemented

### Phase 1 — availability foundation
- Laravel 13 / PHP 8.3+
- MySQL configuration
- room types and physical rooms
- maintenance room blocks
- rate-plan and dated-rate schema
- reservations and reservation room lines
- expiring reservation holds
- overlap-based availability calculation
- transaction + row lock for hold creation

### Phase 2 — guest booking conversion
- adult/child counts carried into the inventory hold
- configured room-capacity enforcement when capacity values exist
- guest details form
- active hold validation
- one-time hold-to-reservation conversion under a database transaction
- unique public booking number
- primary guest linkage
- booking confirmation page
- pricing status kept explicitly pending until real hotel pricing/tax rules are configured

Only confirmed room category names are seeded: Classic, Club and Premium. No room count, room number, rate, occupancy limit, tax, payment setting or hotel policy is invented.

## Local setup

```bash
cd booking
composer install
copy .env.example .env
php artisan key:generate
```

Create the MySQL database from `.env`, then:

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

Pricing/tax configuration, payment workflow, front desk/stays, folios/invoices, restaurant POS/KOT, admin authentication and reports.
