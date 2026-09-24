<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reservation_night_rates', function (Blueprint $table) {
            $table->decimal('tax_rate', 7, 4)->default(0)->after('line_total');
            $table->decimal('tax_amount', 12, 2)->default(0)->after('tax_rate');
            $table->decimal('gross_total', 12, 2)->default(0)->after('tax_amount');
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->string('housekeeping_status', 30)->default('clean')->after('status');
            $table->index(['status', 'housekeeping_status']);
        });

        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->string('status', 30)->default('available')->after('capacity');
            $table->index(['is_active', 'status']);
        });

        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->string('credit_note_number', 50)->unique();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('refund_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('reason', 255)->nullable();
            $table->timestamp('issued_at');
            $table->timestamps();
        });

        Schema::create('credit_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_id')->constrained()->cascadeOnDelete();
            $table->string('description', 255);
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_note_items');
        Schema::dropIfExists('credit_notes');

        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'status']);
            $table->dropColumn('status');
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropIndex(['status', 'housekeeping_status']);
            $table->dropColumn('housekeeping_status');
        });

        Schema::table('reservation_night_rates', function (Blueprint $table) {
            $table->dropColumn(['tax_rate', 'tax_amount', 'gross_total']);
        });
    }
};
