<?php

use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FrontDeskController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RestaurantController;
use App\Http\Controllers\Admin\SetupController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\RazorpayPaymentController;
use App\Http\Controllers\GuestDetailsController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\ReservationHoldController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('health');

Route::get('/', [AvailabilityController::class, 'index'])->name('booking.search');
Route::get('/availability', [AvailabilityController::class, 'search'])->name('booking.availability');
Route::post('/holds', [ReservationHoldController::class, 'store'])->middleware('throttle:5,1')->name('booking.holds.store');
Route::get('/holds/{token}/guest', [GuestDetailsController::class, 'show'])->name('booking.guest');
Route::post('/holds/{token}/confirm', [ReservationController::class, 'store'])->middleware('throttle:10,1')->name('booking.confirm');
Route::get('/confirmation/{token}', [ReservationController::class, 'show'])->name('booking.confirmation');
Route::get('/payment/{token}/razorpay', [RazorpayPaymentController::class, 'start'])->middleware('throttle:10,1')->name('booking.payment.razorpay');
Route::post('/payment/{token}/razorpay/verify', [RazorpayPaymentController::class, 'verify'])->middleware('throttle:10,1')->name('booking.payment.razorpay.verify');
Route::post('/payments/razorpay/webhook', [RazorpayPaymentController::class, 'webhook'])->name('booking.payment.razorpay.webhook');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'show'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:6,1')->name('login.submit');

    Route::middleware(['admin', 'admin.audit'])->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::middleware('admin.role:administrator')->group(function () {
            Route::get('/setup', [SetupController::class, 'index'])->name('setup');
            Route::post('/setup/room-types/{roomType}', [SetupController::class, 'updateRoomType'])->name('setup.room-types.update');
            Route::post('/setup/rooms', [SetupController::class, 'storeRoom'])->name('setup.rooms.store');
            Route::post('/setup/room-blocks', [SetupController::class, 'storeRoomBlock'])->name('setup.room-blocks.store');
            Route::post('/setup/room-blocks/{roomBlock}/close', [SetupController::class, 'closeRoomBlock'])->name('setup.room-blocks.close');
            Route::post('/setup/rate-plans', [SetupController::class, 'storeRatePlan'])->name('setup.rate-plans.store');
            Route::post('/setup/room-rates', [SetupController::class, 'storeRoomRate'])->name('setup.room-rates.store');
            Route::post('/setup/tax-rules', [SetupController::class, 'storeTaxRule'])->name('setup.tax-rules.store');
            Route::post('/setup/restaurant/categories', [SetupController::class, 'storeRestaurantCategory'])->name('setup.restaurant-categories.store');
            Route::post('/setup/restaurant/menu-items', [SetupController::class, 'storeRestaurantMenuItem'])->name('setup.restaurant-menu-items.store');
            Route::post('/setup/restaurant/tables', [SetupController::class, 'storeRestaurantTable'])->name('setup.restaurant-tables.store');

            Route::get('/users', [AdminUserController::class, 'index'])->name('users');
            Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
            Route::post('/users/{adminUser}', [AdminUserController::class, 'update'])->name('users.update');
            Route::post('/users/{adminUser}/password', [AdminUserController::class, 'resetPassword'])->name('users.password');
        });

        Route::middleware('admin.role:administrator,front_desk')->group(function () {
            Route::get('/front-desk', [FrontDeskController::class, 'index'])->name('front-desk');
            Route::post('/front-desk/reservations/{reservation}/modify', [FrontDeskController::class, 'modifyReservation'])->name('front-desk.modify');
            Route::post('/front-desk/reservations/{reservation}/check-in', [FrontDeskController::class, 'checkIn'])->name('front-desk.check-in');
            Route::post('/front-desk/reservations/{reservation}/cancel', [FrontDeskController::class, 'cancel'])->name('front-desk.cancel');
            Route::post('/front-desk/reservations/{reservation}/no-show', [FrontDeskController::class, 'noShow'])->name('front-desk.no-show');
            Route::post('/front-desk/stays/{stay}/transfer', [FrontDeskController::class, 'transfer'])->name('front-desk.transfer');
            Route::post('/front-desk/stays/{stay}/extend', [FrontDeskController::class, 'extend'])->name('front-desk.extend');
            Route::post('/front-desk/stays/{stay}/check-out', [FrontDeskController::class, 'checkOut'])->name('front-desk.check-out');
            Route::post('/front-desk/rooms/{room}/housekeeping', [FrontDeskController::class, 'housekeeping'])->name('front-desk.housekeeping');
        });

        Route::middleware('admin.role:administrator,restaurant,kitchen')->group(function () {
            Route::get('/restaurant', [RestaurantController::class, 'index'])->name('restaurant');
            Route::post('/restaurant/orders/{order}/status', [RestaurantController::class, 'status'])->name('restaurant.orders.status');
        });

        Route::middleware('admin.role:administrator,restaurant')->group(function () {
            Route::post('/restaurant/orders', [RestaurantController::class, 'store'])->name('restaurant.orders.store');
        });

        Route::middleware('admin.role:administrator,front_desk,accounts,restaurant')->group(function () {
            Route::get('/payments', [PaymentController::class, 'index'])->name('payments');
            Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
        });

        Route::middleware('admin.role:administrator,front_desk,accounts')->group(function () {
            Route::post('/payments/{payment}/refund', [PaymentController::class, 'refund'])->name('payments.refund');
        });

        Route::middleware('admin.role:administrator,front_desk,accounts')->group(function () {
            Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
            Route::get('/reports', [ReportController::class, 'index'])->name('reports');
        });
    });
});
