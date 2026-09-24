<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reservation_holds', function (Blueprint $table) {
            $table->unsignedSmallInteger('adults')->default(1)->after('quantity');
            $table->unsignedSmallInteger('children')->default(0)->after('adults');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->string('pricing_status', 30)->default('pending')->after('payment_status');
        });

        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('phone', 30);
            $table->string('email', 190)->nullable();
            $table->timestamps();

            $table->index('phone');
            $table->index('email');
        });

        Schema::create('reservation_guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->constrained()->restrictOnDelete();
            $table->string('role', 30)->default('primary');
            $table->timestamps();

            $table->unique(['reservation_id', 'guest_id']);
            $table->index(['reservation_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_guests');
        Schema::dropIfExists('guests');

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('pricing_status');
        });

        Schema::table('reservation_holds', function (Blueprint $table) {
            $table->dropColumn(['adults', 'children']);
        });
    }
};
