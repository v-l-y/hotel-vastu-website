<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reservation_holds', function (Blueprint $table) {
            $table->foreignId('rate_plan_id')
                ->nullable()
                ->after('room_type_id')
                ->constrained()
                ->nullOnDelete();
        });

        Schema::create('tax_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('applies_to', 30);
            $table->decimal('rate_percent', 7, 4);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['applies_to', 'is_active']);
        });

        Schema::create('reservation_night_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_room_id')->constrained('reservation_rooms')->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('rate_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->date('stay_date');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('unit_rate', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
            $table->unique(['reservation_room_id', 'stay_date']);
        });

        Schema::create('stays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->unique()->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('checked_in');
            $table->timestamp('checked_in_at');
            $table->timestamp('checked_out_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stay_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stay_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->index(['room_id', 'released_at']);
        });

        Schema::create('stay_guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stay_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->constrained()->restrictOnDelete();
            $table->string('role', 30)->default('guest');
            $table->timestamps();
            $table->unique(['stay_id', 'guest_id']);
        });

        Schema::create('folios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stay_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('reservation_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('open');
            $table->decimal('charges_total', 12, 2)->default(0);
            $table->decimal('payments_total', 12, 2)->default(0);
            $table->decimal('refunds_total', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('folio_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folio_id')->constrained()->cascadeOnDelete();
            $table->string('category', 40);
            $table->string('description', 255);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('amount', 12, 2);
            $table->string('source_key', 120)->nullable()->unique();
            $table->timestamps();
            $table->index(['folio_id', 'category']);
        });

        Schema::create('restaurant_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('restaurant_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_category_id')->constrained()->restrictOnDelete();
            $table->string('name', 160);
            $table->decimal('price', 12, 2);
            $table->boolean('is_vegetarian')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['restaurant_category_id', 'is_active']);
        });

        Schema::create('restaurant_tables', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 100);
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('restaurant_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 50)->unique();
            $table->string('order_type', 30);
            $table->foreignId('folio_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('restaurant_table_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_name', 160)->nullable();
            $table->string('guest_phone', 30)->nullable();
            $table->string('status', 30)->default('accepted');
            $table->string('payment_status', 30)->default('unpaid');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamps();
            $table->index(['order_type', 'status']);
        });

        Schema::create('restaurant_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('restaurant_menu_item_id')->constrained()->restrictOnDelete();
            $table->string('item_name', 160);
            $table->unsignedSmallInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('kitchen_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 50)->unique();
            $table->foreignId('restaurant_order_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('served_at')->nullable();
            $table->timestamps();
        });

        Schema::create('kitchen_ticket_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kitchen_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('restaurant_order_item_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['kitchen_ticket_id', 'restaurant_order_item_id'], 'kot_item_unique');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('folio_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('restaurant_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 30);
            $table->string('status', 30)->default('succeeded');
            $table->decimal('amount', 12, 2);
            $table->string('external_reference', 190)->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'paid_at']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('status', 30)->default('succeeded');
            $table->string('reason', 255)->nullable();
            $table->string('external_reference', 190)->nullable()->unique();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 50)->unique();
            $table->foreignId('folio_id')->constrained()->restrictOnDelete();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax', 12, 2);
            $table->decimal('total', 12, 2);
            $table->decimal('paid', 12, 2);
            $table->decimal('balance', 12, 2);
            $table->timestamp('issued_at');
            $table->timestamps();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('category', 40);
            $table->string('description', 255);
            $table->unsignedInteger('quantity');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax', 12, 2);
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });

        Schema::create('admin_users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 190)->unique();
            $table->string('password_hash');
            $table->string('role', 40);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_users');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('kitchen_ticket_items');
        Schema::dropIfExists('kitchen_tickets');
        Schema::dropIfExists('restaurant_order_items');
        Schema::dropIfExists('restaurant_orders');
        Schema::dropIfExists('restaurant_tables');
        Schema::dropIfExists('restaurant_menu_items');
        Schema::dropIfExists('restaurant_categories');
        Schema::dropIfExists('folio_charges');
        Schema::dropIfExists('folios');
        Schema::dropIfExists('stay_guests');
        Schema::dropIfExists('stay_rooms');
        Schema::dropIfExists('stays');
        Schema::dropIfExists('reservation_night_rates');
        Schema::dropIfExists('tax_rules');

        Schema::table('reservation_holds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rate_plan_id');
        });
    }
};
