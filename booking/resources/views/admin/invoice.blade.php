@extends('admin.layout')
@section('title', ($invoice->is_gst_invoice ? 'Tax Invoice ' : 'Invoice ').$invoice->invoice_number)
@section('content')
@include('invoices.official')
@endsection
