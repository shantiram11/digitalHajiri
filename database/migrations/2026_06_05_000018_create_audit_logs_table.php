<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Append-only audit log. Never updated, never deleted.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()
                ->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()
                ->constrained()->nullOnDelete();

            $table->string('action', 64)
                ->comment('created|updated|deleted|login|logout|impersonate|sync|...');
            $table->morphs('auditable');

            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('context')->nullable()->comment('Additional structured context (reason, request id, etc.)');

            $table->string('ip', 64)->nullable();
            $table->string('user_agent', 512)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'created_at'], 'al_org_created_idx');
            $table->index(['user_id', 'created_at'], 'al_user_created_idx');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
