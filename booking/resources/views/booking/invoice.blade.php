<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>{{ $invoice->is_gst_invoice ? 'Tax Invoice' : 'Invoice' }} {{ $invoice->invoice_number }} | Hotel Vastu Premium</title>
<style>
*{box-sizing:border-box}body{font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;margin:0;background:#f5f2ee;color:#181512}button{font:inherit;min-height:42px;border:0;border-radius:9px;padding:8px 14px;background:#2b211b;color:#fff;font-weight:800;cursor:pointer}main{padding:24px 16px 56px}
</style>
</head>
<body>
@include('partials.toast')
<main>
@include('invoices.official')
</main>
@stack('styles')
</body>
</html>
