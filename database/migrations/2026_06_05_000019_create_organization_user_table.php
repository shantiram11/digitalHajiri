<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-organization membership join.
 *
 * Authority on "which orgs can this user access" — `users.organization_id` is
 * only a *default* pointer for SPA login UX.
 *
 * See docs/authorization.md §9 for the full reasoning.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('organization_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('status', 32)->default('active')
                ->comment('invited|active|suspended');

            $table->timestamp('joined_at')->nullable();
            $table->foreignId('invited_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('default_role_id')->nullable()
                ->comment('The org role assigned at provision/invitation time')
                ->constrained('roles')->nullOnDelete();

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'user_id'], 'org_user_unique');
            $table->index(['user_id', 'status']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_user');
    }
};
