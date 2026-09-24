<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->string('refund_type', 40)->default('other')->after('status');
            $table->index(['refund_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropIndex(['refund_type', 'status']);
            $table->dropColumn('refund_type');
        });
    }
};
