<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pending invitations.
 *
 * Acceptance creates a `organization_user` row + assigns the requested role.
 * See docs/authorization.md §10 for the full flow and security reasoning.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('organization_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->foreignId('role_id')->nullable()
                ->constrained('roles')->nullOnDelete();

            $table->char('token', 64)->unique();
            $table->timestamp('expires_at');

            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'email']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_invitations');
    }
};
