<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();

            $table->date('work_date')->comment('AD');
            $table->timestamp('requested_check_in')->nullable();
            $table->timestamp('requested_check_out')->nullable();
            $table->string('correction_type', 32)->default('missed_punch')
                ->comment('missed_punch|wrong_timestamp|device_failure|other');
            $table->text('reason');
            $table->string('attachment_path')->nullable();

            $table->string('status', 32)->default('pending')
                ->comment('pending|approved|rejected|cancelled');
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_comment')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['employee_id', 'work_date']);
        });

        Schema::create('attendance_correction_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('correction_id')->constrained('attendance_corrections')->cascadeOnDelete();
            $table->unsignedSmallInteger('step');
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('pending');
            $table->timestamp('acted_at')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['correction_id', 'step']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_correction_approvals');
        Schema::dropIfExists('attendance_corrections');
    }
};
