<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('target_type', 64)
                ->comment('leave_request|attendance_correction');
            $table->json('steps')
                ->comment('Ordered list of step definitions: role/user/department-head');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'target_type', 'is_active'], 'aw_org_target_active_idx');
        });

        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_id')->nullable()->constrained('approval_workflows')->nullOnDelete();
            $table->morphs('approvable');
            $table->unsignedSmallInteger('step');
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('pending')
                ->comment('pending|approved|rejected|skipped');
            $table->timestamp('acted_at')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'ap_org_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('approval_workflows');
    }
};
