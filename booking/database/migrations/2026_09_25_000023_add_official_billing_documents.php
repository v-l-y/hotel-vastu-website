<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        // If a prior attempt partially changed the database but was not recorded in
        // the migrations table, let the following repair migration reconcile the
        // entire billing schema instead of failing here on duplicate objects.
        $partiallyApplied = Schema::hasTable('billing_document_sequences')
            || (Schema::hasTable('guests') && Schema::hasColumn('guests', 'gstin'))
            || (Schema::hasTable('booking_verifications') && Schema::hasColumn('booking_verifications', 'gstin'))
            || (Schema::hasTable('restaurant_orders') && Schema::hasColumn('restaurant_orders', 'guest_email'))
            || (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'public_token'))
            || (Schema::hasTable('invoice_items') && Schema::hasColumn('invoice_items', 'sac_code'));

        if ($partiallyApplied) {
            return;
        }

        Schema::create('billing_document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('series', 2);
            $table->string('financial_year', 5);
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['series', 'financial_year']);
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->string('gstin', 20)->nullable()->after('email');
            $table->text('billing_address')->nullable()->after('gstin');
            $table->string('billing_state', 100)->nullable()->after('billing_address');
            $table->string('billing_state_code', 2)->nullable()->after('billing_state');
        });

        Schema::table('booking_verifications', function (Blueprint $table) {
            $table->string('gstin', 20)->nullable()->after('email');
            $table->text('billing_address')->nullable()->after('gstin');
            $table->string('billing_state', 100)->nullable()->after('billing_address');
            $table->string('billing_state_code', 2)->nullable()->after('billing_state');
        });

        Schema::table('restaurant_orders', function (Blueprint $table) {
            $table->string('guest_email', 190)->nullable()->after('guest_phone');
            $table->string('guest_gstin', 20)->nullable()->after('guest_email');
            $table->text('guest_billing_address')->nullable()->after('guest_gstin');
            $table->string('guest_billing_state', 100)->nullable()->after('guest_billing_address');
            $table->string('guest_billing_state_code', 2)->nullable()->after('guest_billing_state');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('folio_id')->nullable()->change();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->uuid('public_token')->nullable()->unique()->after('invoice_number');
            $table->string('document_type', 30)->default('hotel')->after('public_token');
            $table->foreignId('restaurant_order_id')
                ->nullable()
                ->after('folio_id')
                ->constrained()
                ->restrictOnDelete();
            $table->unique('restaurant_order_id');

            $table->boolean('is_gst_invoice')->default(false)->after('restaurant_order_id');
            $table->string('supplier_name', 160)->nullable()->after('is_gst_invoice');
            $table->text('supplier_address')->nullable()->after('supplier_name');
            $table->string('supplier_gstin', 20)->nullable()->after('supplier_address');
            $table->string('supplier_state', 100)->nullable()->after('supplier_gstin');
            $table->string('supplier_state_code', 2)->nullable()->after('supplier_state');
            $table->string('supplier_phone', 30)->nullable()->after('supplier_state_code');
            $table->string('supplier_email', 190)->nullable()->after('supplier_phone');

            $table->string('recipient_name', 200)->nullable()->after('supplier_email');
            $table->string('recipient_phone', 30)->nullable()->after('recipient_name');
            $table->string('recipient_email', 190)->nullable()->after('recipient_phone');
            $table->string('recipient_gstin', 20)->nullable()->after('recipient_email');
            $table->text('recipient_address')->nullable()->after('recipient_gstin');
            $table->string('recipient_state', 100)->nullable()->after('recipient_address');
            $table->string('recipient_state_code', 2)->nullable()->after('recipient_state');

            $table->string('place_of_supply', 100)->nullable()->after('recipient_state_code');
            $table->string('place_of_supply_code', 2)->nullable()->after('place_of_supply');
            $table->boolean('reverse_charge')->default(false)->after('place_of_supply_code');

            $table->decimal('cgst', 12, 2)->default(0)->after('tax');
            $table->decimal('sgst', 12, 2)->default(0)->after('cgst');
            $table->decimal('igst', 12, 2)->default(0)->after('sgst');
            $table->timestamp('last_sent_at')->nullable()->after('issued_at');
        });

        DB::table('invoices')
            ->whereNull('public_token')
            ->orderBy('id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('invoices')
                        ->where('id', $row->id)
                        ->update(['public_token' => (string) Str::uuid()]);
                }
            });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('sac_code', 20)->nullable()->after('description');
            $table->decimal('tax_rate_percent', 7, 4)->default(0)->after('subtotal');
            $table->decimal('cgst_rate_percent', 7, 4)->default(0)->after('tax');
            $table->decimal('cgst_amount', 12, 2)->default(0)->after('cgst_rate_percent');
            $table->decimal('sgst_rate_percent', 7, 4)->default(0)->after('cgst_amount');
            $table->decimal('sgst_amount', 12, 2)->default(0)->after('sgst_rate_percent');
            $table->decimal('igst_rate_percent', 7, 4)->default(0)->after('sgst_amount');
            $table->decimal('igst_amount', 12, 2)->default(0)->after('igst_rate_percent');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn([
                'sac_code',
                'tax_rate_percent',
                'cgst_rate_percent',
                'cgst_amount',
                'sgst_rate_percent',
                'sgst_amount',
                'igst_rate_percent',
                'igst_amount',
            ]);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['restaurant_order_id']);
            $table->dropConstrainedForeignId('restaurant_order_id');
            $table->dropUnique(['public_token']);
            $table->dropColumn([
                'public_token',
                'document_type',
                'is_gst_invoice',
                'supplier_name',
                'supplier_address',
                'supplier_gstin',
                'supplier_state',
                'supplier_state_code',
                'supplier_phone',
                'supplier_email',
                'recipient_name',
                'recipient_phone',
                'recipient_email',
                'recipient_gstin',
                'recipient_address',
                'recipient_state',
                'recipient_state_code',
                'place_of_supply',
                'place_of_supply_code',
                'reverse_charge',
                'cgst',
                'sgst',
                'igst',
                'last_sent_at',
            ]);
        });

        Schema::table('restaurant_orders', function (Blueprint $table) {
            $table->dropColumn([
                'guest_email',
                'guest_gstin',
                'guest_billing_address',
                'guest_billing_state',
                'guest_billing_state_code',
            ]);
        });

        Schema::table('booking_verifications', function (Blueprint $table) {
            $table->dropColumn(['gstin', 'billing_address', 'billing_state', 'billing_state_code']);
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn(['gstin', 'billing_address', 'billing_state', 'billing_state_code']);
        });

        Schema::dropIfExists('billing_document_sequences');
    }
};
