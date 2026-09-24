<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Booking confirmed | Hotel Vastu Premium</title>
<style>
body{font-family:system-ui,sans-serif;margin:0;background:#f6f3ef;color:#241f1b}main{max-width:760px;margin:auto;padding:32px 20px 64px}.panel{background:#fff;border:1px solid #ded8d1;border-radius:16px;padding:24px}.booking{font-size:1.5rem;font-weight:800;letter-spacing:.04em}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:20px}.grid div{border:1px solid #e2ddd7;border-radius:10px;padding:12px}.amount{font-size:1.3rem;font-weight:750}.note{margin-top:20px;padding:14px;border-radius:8px;background:#eef6ee}@media(max-width:640px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<main>
<p>Hotel Vastu Premium</p>
<h1>Booking confirmed</h1>
<p class="booking">{{ $reservation->booking_number }}</p>

@php($primaryGuest = optional($reservation->guestLinks->first())->guest)
<section class="panel">
<div class="grid">
<div><strong>Guest</strong><br>{{ trim(($primaryGuest->first_name ?? '').' '.($primaryGuest->last_name ?? '')) }}</div>
<div><strong>Check-in</strong><br>{{ $reservation->check_in_date->format('d M Y') }}</div>
<div><strong>Check-out</strong><br>{{ $reservation->check_out_date->format('d M Y') }}</div>
<div><strong>Guests</strong><br>{{ $reservation->adults }} adult(s), {{ $reservation->children }} child(ren)</div>
<div><strong>Status</strong><br>{{ ucfirst($reservation->status) }}</div>
<div><strong>Payment</strong><br>{{ str_replace('_', ' ', ucfirst($reservation->payment_status)) }}</div>
</div>

<p class="amount">Total: ₹{{ number_format((float) $reservation->total, 2) }}</p>
<p>Room ₹{{ number_format((float) $reservation->subtotal, 2) }} + tax ₹{{ number_format((float) $reservation->tax, 2) }}</p>
<p class="note">Your room inventory and quoted price have been saved with this booking. Payment can be collected through the configured hotel payment flow.</p>
</section>
</main>
</body>
</html>
