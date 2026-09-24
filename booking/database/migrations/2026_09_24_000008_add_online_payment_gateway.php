<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_gateway_orders', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30);
            $table->foreignId('reservation_id')->constrained()->restrictOnDelete();
            $table->string('provider_order_id', 100)->unique();
            $table->string('provider_payment_id', 100)->nullable()->unique();
            $table->unsignedBigInteger('amount_subunits');
            $table->string('currency', 3)->default('INR');
            $table->string('status', 30)->default('created');
            $table->timestamps();

            $table->index(['provider', 'reservation_id', 'status']);
        });

        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30);
            $table->char('event_hash', 64)->unique();
            $table->string('event_name', 100);
            $table->string('provider_order_id', 100)->nullable();
            $table->string('provider_payment_id', 100)->nullable();
            $table->timestamp('processed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payment_gateway_orders');
    }
};
