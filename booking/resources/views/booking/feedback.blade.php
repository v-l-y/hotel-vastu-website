<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Rate your stay | Hotel Vastu Premium</title>
<style>
body{font-family:system-ui,sans-serif;margin:0;background:#f6f3ef;color:#241f1b}main{max-width:680px;margin:auto;padding:32px 20px 64px}.panel{background:#fff;border:1px solid #ded8d1;border-radius:16px;padding:24px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}label{display:grid;gap:7px;font-weight:650}select,textarea,button{font:inherit}select,textarea{border:1px solid #b9b0a7;border-radius:8px;padding:10px 12px}button{min-height:44px;border:0;border-radius:8px;padding:0 18px;background:#2b211b;color:#fff;cursor:pointer}.full{grid-column:1/-1}.notice{padding:12px 14px;border-radius:8px;background:#eef6ee}.error{padding:12px 14px;border-radius:8px;background:#fff0f0;color:#791717}@media(max-width:640px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
@include('partials.toast')
<main>
<p>Hotel Vastu Premium</p>
<h1>Rate your stay</h1>
<p>Booking <strong>{{ $feedback->reservation->booking_number }}</strong></p>

@if($feedback->submitted_at)
<section class="panel"><h2>Thank you</h2><p>Your feedback has been received.</p></section>
@elseif($feedback->reservation->status!=='checked_out')
<section class="panel"><p>Feedback becomes available after checkout.</p></section>
@else
<section class="panel">
<form class="grid" method="post" action="{{ route('booking.feedback.store',['token'=>$feedback->token]) }}">@csrf
@foreach([
  'overall_rating'=>'Overall rating',
  'cleanliness_rating'=>'Room cleanliness',
  'service_rating'=>'Staff / service'
] as $field=>$label)
<label>{{ $label }}
<select name="{{ $field }}" required>
<option value="">Choose</option>
@for($i=5;$i>=1;$i--)<option value="{{ $i }}" @selected(old($field)==$i)>{{ $i }} / 5</option>@endfor
</select>
</label>
@endforeach
<label>Food <span style="font-weight:400">(optional)</span>
<select name="food_rating"><option value="">Not applicable</option>@for($i=5;$i>=1;$i--)<option value="{{ $i }}" @selected(old('food_rating')==$i)>{{ $i }} / 5</option>@endfor</select>
</label>
<label class="full">Comment <span style="font-weight:400">(optional)</span><textarea name="comment" rows="5">{{ old('comment') }}</textarea></label>
<div class="full"><button type="submit">Submit feedback</button></div>
</form>
</section>
@endif
</main>
</body>
</html>
