<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('booking_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_hold_id')->unique()->constrained('reservation_holds')->cascadeOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('phone', 30);
            $table->string('email', 190)->nullable();
            $table->text('special_request')->nullable();
            $table->string('code_hash');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->index(['phone', 'expires_at']);
        });

        Schema::create('reservation_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->unique()->constrained()->cascadeOnDelete();
            $table->uuid('token')->unique();
            $table->unsignedTinyInteger('overall_rating')->nullable();
            $table->unsignedTinyInteger('cleanliness_rating')->nullable();
            $table->unsignedTinyInteger('service_rating')->nullable();
            $table->unsignedTinyInteger('food_rating')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_feedbacks');
        Schema::dropIfExists('booking_verifications');
    }
};
