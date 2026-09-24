<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('room_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->unsignedSmallInteger('max_adults')->nullable();
            $table->unsignedSmallInteger('max_children')->nullable();
            $table->decimal('base_rate', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained()->restrictOnDelete();
            $table->string('number', 30)->unique();
            $table->string('floor', 50)->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();
            $table->index(['room_type_id', 'status']);
        });

        Schema::create('room_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('reason', 255)->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();
            $table->index(['room_id', 'starts_on', 'ends_on']);
        });

        Schema::create('rate_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->boolean('includes_breakfast')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('room_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rate_plan_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('nightly_rate', 12, 2);
            $table->unsignedSmallInteger('min_stay')->default(1);
            $table->unsignedSmallInteger('max_stay')->nullable();
            $table->timestamps();
            $table->index(['room_type_id', 'starts_on', 'ends_on']);
        });

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('booking_number', 40)->unique();
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->unsignedSmallInteger('adults')->default(1);
            $table->unsignedSmallInteger('children')->default(0);
            $table->string('status', 30)->default('pending');
            $table->string('source', 30)->default('website');
            $table->text('special_request')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('payment_status', 30)->default('unpaid');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['check_in_date', 'check_out_date', 'status']);
        });

        Schema::create('reservation_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('rate_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('nightly_rate', 12, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('reservation_holds', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('room_type_id')->constrained()->restrictOnDelete();
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->timestamp('expires_at');
            $table->foreignId('converted_reservation_id')->nullable()->constrained('reservations')->nullOnDelete();
            $table->timestamps();
            $table->index(
                ['room_type_id', 'check_in_date', 'check_out_date', 'expires_at'],
                'reservation_holds_availability_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_holds');
        Schema::dropIfExists('reservation_rooms');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('room_rates');
        Schema::dropIfExists('rate_plans');
        Schema::dropIfExists('room_blocks');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('room_types');
    }
};
