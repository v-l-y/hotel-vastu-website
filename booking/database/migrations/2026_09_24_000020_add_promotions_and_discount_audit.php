<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('promotion_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->string('discount_type', 20);
            $table->decimal('discount_value', 12, 2);
            $table->decimal('max_discount', 12, 2)->nullable();
            $table->decimal('min_subtotal', 12, 2)->default(0);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('times_used')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'starts_on', 'ends_on']);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('promotion_code_id')
                ->nullable()
                ->after('discount')
                ->constrained('promotion_codes')
                ->nullOnDelete();
            $table->string('promotion_code_snapshot', 40)->nullable()->after('promotion_code_id');
            $table->string('discount_source', 20)->nullable()->after('promotion_code_snapshot');
            $table->string('discount_type', 20)->nullable()->after('discount_source');
            $table->decimal('discount_value', 12, 2)->nullable()->after('discount_type');
            $table->decimal('discount_max', 12, 2)->nullable()->after('discount_value');
            $table->string('discount_reason', 255)->nullable()->after('discount_max');
            $table->unsignedBigInteger('discount_authorized_by')->nullable()->after('discount_reason');
            $table->index(['discount_source', 'promotion_code_snapshot']);
        });

        Schema::table('booking_verifications', function (Blueprint $table) {
            $table->string('promo_code', 40)->nullable()->after('special_request');
        });
    }

    public function down(): void
    {
        Schema::table('booking_verifications', function (Blueprint $table) {
            $table->dropColumn('promo_code');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['promotion_code_id']);
            $table->dropIndex(['discount_source', 'promotion_code_snapshot']);
            $table->dropColumn([
                'promotion_code_id',
                'promotion_code_snapshot',
                'discount_source',
                'discount_type',
                'discount_value',
                'discount_max',
                'discount_reason',
                'discount_authorized_by',
            ]);
        });

        Schema::dropIfExists('promotion_codes');
    }
};
