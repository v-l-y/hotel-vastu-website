<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('room_blocks', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable()->after('status');
            $table->index(
                ['room_id', 'starts_on', 'ends_on', 'closed_at'],
                'room_blocks_reporting_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('room_blocks', function (Blueprint $table) {
            $table->dropIndex('room_blocks_reporting_idx');
            $table->dropColumn('closed_at');
        });
    }
};
