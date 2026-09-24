<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->unsignedInteger('session_version')->default(1)->after('is_active');
            $table->text('two_factor_secret')->nullable()->after('session_version');
            $table->timestamp('two_factor_enabled_at')->nullable()->after('two_factor_secret');
            $table->timestamp('password_changed_at')->nullable()->after('last_login_at');
        });

        Schema::create('admin_auth_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 50);
            $table->char('email_hash', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->char('user_agent_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['event_type', 'created_at']);
            $table->index(['admin_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_auth_events');

        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropColumn([
                'session_version',
                'two_factor_secret',
                'two_factor_enabled_at',
                'password_changed_at',
            ]);
        });
    }
};
