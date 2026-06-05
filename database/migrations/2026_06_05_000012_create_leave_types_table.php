<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug', 64);
            $table->string('description')->nullable();
            $table->decimal('default_quota_days', 6, 2)->default(0);
            $table->boolean('is_paid')->default(true);
            $table->boolean('counts_weekly_off')->default(false);
            $table->boolean('counts_holidays')->default(false);
            $table->unsignedSmallInteger('requires_attachment_after_days')->nullable();
            $table->boolean('allow_half_day')->default(true);
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
