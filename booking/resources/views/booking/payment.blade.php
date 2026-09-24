<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Secure payment | Hotel Vastu Premium</title>
<style>
body{font-family:system-ui,sans-serif;margin:0;background:#f6f3ef;color:#241f1b}main{max-width:680px;margin:auto;padding:40px 20px}.panel{background:#fff;border:1px solid #ded8d1;border-radius:16px;padding:24px}button{min-height:46px;border:0;border-radius:8px;padding:0 20px;background:#2b211b;color:#fff;font:inherit;cursor:pointer}.amount{font-size:1.5rem;font-weight:800}
</style>
</head>
<body>
@include('partials.toast')
<main>
<p>Hotel Vastu Premium</p>
<h1>Secure online payment</h1>
<section class="panel">
<p>Booking <strong>{{ $reservation->booking_number }}</strong></p>
<p class="amount">Amount due: ₹{{ number_format($order->amount_subunits / 100, 2) }}</p>
<p>You will complete payment in Razorpay Checkout. The booking system verifies the returned signature and captured payment before marking this booking paid.</p>
<button type="button" id="pay-now">Pay now</button>
<p><a href="{{ route('booking.confirmation',['token'=>$reservation->public_token]) }}">Back to booking</a></p>
</section>
</main>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
(() => {
  const button = document.getElementById('pay-now');
  const options = {
    key: @json($keyId),
    amount: {{ $order->amount_subunits }},
    currency: 'INR',
    name: 'Hotel Vastu Premium',
    description: @json('Booking '.$reservation->booking_number),
    order_id: @json($order->provider_order_id),
    handler(response) {
      const form = document.createElement('form');
      form.method = 'post';
      form.action = @json(route('booking.payment.razorpay.verify',['token'=>$reservation->public_token]));
      const fields = {
        _token: @json(csrf_token()),
        razorpay_order_id: response.razorpay_order_id,
        razorpay_payment_id: response.razorpay_payment_id,
        razorpay_signature: response.razorpay_signature,
      };
      Object.entries(fields).forEach(([name,value]) => {
        const input = document.createElement('input');
        input.type = 'hidden'; input.name = name; input.value = value;
        form.appendChild(input);
      });
      document.body.appendChild(form);
      form.submit();
    },
    theme: { color: '#2b211b' }
  };
  button.addEventListener('click', () => new Razorpay(options).open());
})();
</script>
</body>
</html>
