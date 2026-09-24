<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Guest details | Hotel Vastu Premium</title>
<style>
body{font-family:system-ui,sans-serif;margin:0;background:#f6f3ef;color:#241f1b}main{max-width:760px;margin:auto;padding:32px 20px 64px}.panel{background:#fff;border:1px solid #ded8d1;border-radius:16px;padding:24px}.summary{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:20px 0}.summary div{border:1px solid #e2ddd7;border-radius:10px;padding:12px}.price-box{margin:0 0 20px;background:#fff;border:1px solid #ded8d1;border-radius:16px;padding:18px 20px}.price-row{display:flex;justify-content:space-between;gap:16px;padding:6px 0}.price-total{margin-top:8px;padding-top:12px;border-top:1px solid #ded8d1;font-size:1.2rem;font-weight:800}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}label{display:grid;gap:7px;font-weight:600}input,textarea,button{font:inherit}input,textarea{border:1px solid #b9b0a7;border-radius:8px;padding:10px 12px}button{min-height:44px;border:0;border-radius:8px;padding:0 18px;background:#2b211b;color:#fff;cursor:pointer}.full{grid-column:1/-1}.error{padding:12px 14px;border-radius:8px;background:#fff0f0;color:#791717}.muted{color:#6d635c}.promo-entry{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px}.promo-entry button{min-height:42px}.promo-result{font-size:.9rem;font-weight:600}.promo-result.good{color:#246b35}.promo-result.bad{color:#8a1c1c}@media(max-width:640px){.summary,.grid{grid-template-columns:1fr}.promo-entry{grid-template-columns:1fr}}
</style>
</head>
<body>
@include('partials.toast')
<main>
<p>Hotel Vastu Premium</p>
<h1>Guest details</h1>
<p>Your selected room is held until {{ $hold->expires_at->format('d M Y, h:i A') }}.</p>

<div class="summary">
<div><strong>{{ $hold->roomType->name }}</strong><br>{{ $hold->ratePlan->name }} · {{ $hold->quantity }} room(s)</div>
<div><strong>{{ $hold->check_in_date->format('d M Y') }} → {{ $hold->check_out_date->format('d M Y') }}</strong><br>{{ $hold->adults }} adult(s), {{ $hold->children }} child(ren)</div>
</div>

<section class="price-box" aria-label="Stay price">
<div class="price-row"><span>Room charges</span><strong>₹{{ number_format((float) $quote['subtotal'], 2) }}</strong></div>
<div class="price-row" data-promo-discount hidden><span>Promo discount</span><strong data-promo-discount-value></strong></div>
<div class="price-row"><span>Tax</span><strong data-price-tax>₹{{ number_format((float) $quote['tax'], 2) }}</strong></div>
<div class="price-row price-total"><span>Stay total</span><span data-price-total>₹{{ number_format((float) $quote['total'], 2) }}</span></div>
<p class="muted">This is the price for the selected dates, room(s) and rate plan.</p>
</section>

<section class="panel">
<form class="grid" method="post" action="{{ route('booking.otp.send', ['token' => $hold->token]) }}">@csrf
<label>First name<input name="first_name" autocomplete="given-name" required value="{{ old('first_name') }}"></label>
<label>Last name<input name="last_name" autocomplete="family-name" value="{{ old('last_name') }}"></label>
<label>Phone<input name="phone" inputmode="tel" autocomplete="tel" required value="{{ old('phone') }}"></label>
<label>Email<input name="email" type="email" autocomplete="email" value="{{ old('email') }}"></label>
<label>Promo code <span class="muted">Optional</span>
<span class="promo-entry">
<input name="promo_code" maxlength="40" autocomplete="off" value="{{ old('promo_code') }}" placeholder="Enter promo code" data-promo-code>
<button type="button" data-promo-preview>Apply</button>
</span>
<span class="promo-result muted" data-promo-result>Apply a code to preview the exact saving before verification.</span>
</label>
<label class="full">Special request<textarea name="special_request" rows="4">{{ old('special_request') }}</textarea></label>
<div class="full"><button type="submit">Send verification code</button></div>
</form>
</section>
</main>
<script>
(() => {
    const code = document.querySelector('[data-promo-code]');
    const button = document.querySelector('[data-promo-preview]');
    const result = document.querySelector('[data-promo-result]');
    const discountRow = document.querySelector('[data-promo-discount]');
    const discountValue = document.querySelector('[data-promo-discount-value]');
    const tax = document.querySelector('[data-price-tax]');
    const total = document.querySelector('[data-price-total]');
    const csrf = document.querySelector('input[name="_token"]')?.value;
    if (!code || !button || !result || !discountRow || !discountValue || !tax || !total || !csrf) return;

    const originalTax = @json((float) $quote['tax']);
    const originalTotal = @json((float) $quote['total']);
    const money = (value) => new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        minimumFractionDigits: 2,
    }).format(Number(value));

    const reset = () => {
        discountRow.hidden = true;
        tax.textContent = money(originalTax);
        total.textContent = money(originalTotal);
        result.className = 'promo-result muted';
        result.textContent = 'Apply a code to preview the exact saving before verification.';
    };

    code.addEventListener('input', reset);

    button.addEventListener('click', async () => {
        const promoCode = code.value.trim();
        if (!promoCode) {
            reset();
            result.className = 'promo-result bad';
            result.textContent = 'Enter a promo code first.';
            window.HotelToast?.error('Enter a promo code first.');
            return;
        }

        button.disabled = true;
        result.className = 'promo-result muted';
        result.textContent = 'Checking promo…';

        try {
            const response = await fetch(@json(route('booking.promo.preview',['token'=>$hold->token])), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ promo_code: promoCode }),
            });
            const payload = await response.json();

            if (!response.ok) {
                const validationMessage = payload?.errors?.promo_code?.[0];
                throw new Error(validationMessage || payload?.message || 'Promo code could not be applied.');
            }

            code.value = payload.code;
            discountRow.hidden = false;
            discountValue.textContent = '−' + money(payload.discount);
            tax.textContent = money(payload.tax);
            total.textContent = money(payload.total);
            result.className = 'promo-result good';
            result.textContent = payload.code + ' applied · You save ' + money(payload.discount) + '.';
            window.HotelToast?.success(result.textContent);
        } catch (error) {
            reset();
            const message = error instanceof Error ? error.message : 'Promo code could not be applied.';
            result.className = 'promo-result bad';
            result.textContent = message;
            window.HotelToast?.error(message);
        } finally {
            button.disabled = false;
        }
    });
})();
</script>
</body>
</html>
