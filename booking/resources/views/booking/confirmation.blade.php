<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Booking status | Hotel Vastu Premium</title>
<style>
body{font-family:system-ui,sans-serif;margin:0;background:#f6f3ef;color:#241f1b}main{max-width:760px;margin:auto;padding:32px 20px 64px}.panel{background:#fff;border:1px solid #ded8d1;border-radius:16px;padding:24px}.booking{font-size:1.5rem;font-weight:800;letter-spacing:.04em}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:20px}.grid div{border:1px solid #e2ddd7;border-radius:10px;padding:12px}.amount{font-size:1.3rem;font-weight:750}.success,.warning,.note{margin:18px 0;padding:14px 16px;border-radius:10px}.success{background:#eaf7ec;border:1px solid #b9ddbf}.warning{background:#fff5e8;border:1px solid #efd2a7}.note{background:#eef6ee}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}.button{display:inline-block;padding:12px 18px;background:#2b211b;color:#fff;text-decoration:none;border-radius:8px}.button.secondary{background:#fff;color:#2b211b;border:1px solid #b9b0a7}@media(max-width:640px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<main>
<p>Hotel Vastu Premium</p>
<h1>Booking status</h1>
<p class="booking">{{ $reservation->booking_number }}</p>

@php($primaryGuest = optional($reservation->guestLinks->first())->guest)

@if($reservation->status === 'confirmed')
<div class="success">
<strong>Booking confirmed successfully!</strong><br>
Your room has been booked at Hotel Vastu Premium.
@if($primaryGuest?->email)
A confirmation has been sent to your mobile and email.
@else
A confirmation has been sent to your mobile.
@endif
</div>
@elseif($reservation->status === 'no_show')
<div class="warning">
<strong>This reservation was marked as no-show.</strong><br>
No refund is processed automatically. Please contact the hotel for any applicable adjustment.
</div>
@endif

<section class="panel">
<div class="grid">
<div><strong>Guest</strong><br>{{ trim(($primaryGuest->first_name ?? '').' '.($primaryGuest->last_name ?? '')) }}</div>
<div><strong>Check-in</strong><br>{{ $reservation->check_in_date->format('d M Y') }}</div>
<div><strong>Check-out</strong><br>{{ $reservation->check_out_date->format('d M Y') }}</div>
<div><strong>Guests</strong><br>{{ $reservation->adults }} adult(s), {{ $reservation->children }} child(ren)</div>
<div><strong>Status</strong><br>{{ str_replace('_',' ',ucfirst($reservation->status)) }}</div>
<div><strong>Payment</strong><br>{{ str_replace('_', ' ', ucfirst($reservation->payment_status)) }}</div>
</div>

<p class="amount">Total: ₹{{ number_format((float) $reservation->total, 2) }}</p>
<p>Room ₹{{ number_format((float) $reservation->subtotal, 2) }} + tax ₹{{ number_format((float) $reservation->tax, 2) }}</p>
<p class="note">Keep this secure link to check the latest booking status.</p>

<div class="actions">
@if(config('services.razorpay.key_id') && in_array($reservation->payment_status,['unpaid','partially_paid'],true) && $reservation->status==='confirmed')
<a class="button" href="{{ route('booking.payment.razorpay',['token'=>$reservation->public_token]) }}">Pay online securely</a>
@endif
@if($invoice)
<a class="button" href="{{ route('booking.invoice',['token'=>$reservation->public_token]) }}">View invoice</a>
@endif
@if($reservation->status==='checked_out' && $reservation->feedback)
<a class="button secondary" href="{{ route('booking.feedback.show',['token'=>$reservation->feedback->token]) }}">Rate your stay</a>
@endif
</div>
</section>
</main>
</body>
</html>
