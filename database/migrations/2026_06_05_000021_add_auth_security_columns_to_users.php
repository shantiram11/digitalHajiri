<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auth hardening columns documented in docs/authentication.md §5.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('last_login_at')->nullable()->after('status');
            $table->string('last_login_ip', 64)->nullable()->after('last_login_at');
            $table->unsignedSmallInteger('failed_login_attempts')->default(0)->after('last_login_ip');
            $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
            $table->timestamp('password_changed_at')->nullable()->after('locked_until');
            $table->boolean('force_password_change')->default(false)->after('password_changed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'last_login_at',
                'last_login_ip',
                'failed_login_attempts',
                'locked_until',
                'password_changed_at',
                'force_password_change',
            ]);
        });
    }
};
