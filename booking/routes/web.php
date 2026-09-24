<?php

use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\GuestDetailsController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\ReservationHoldController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'service' => 'hotel-vastu-booking',
]));

Route::get('/', [AvailabilityController::class, 'index'])->name('booking.search');
Route::get('/availability', [AvailabilityController::class, 'search'])->name('booking.availability');
Route::post('/holds', [ReservationHoldController::class, 'store'])->name('booking.holds.store');
Route::get('/holds/{token}/guest', [GuestDetailsController::class, 'show'])->name('booking.guest');
Route::post('/holds/{token}/confirm', [ReservationController::class, 'store'])->name('booking.confirm');
Route::get('/confirmation/{bookingNumber}', [ReservationController::class, 'show'])->name('booking.confirmation');
