<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Derived per-employee per-day summaries.
     * Regenerated whenever events, rules, leave, or holidays affecting that
     * (employee, work_date) change. Old summaries are overwritten in place;
     * the audit trail captures the diff.
     */
    public function up(): void
    {
        Schema::create('attendance_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rule_id')->nullable()->constrained('attendance_rules')->nullOnDelete();

            $table->date('work_date')->comment('AD');
            $table->string('status', 32)->default('absent')
                ->comment('present|absent|half_day|on_leave|holiday|weekly_off');
            $table->string('leave_type_slug', 64)->nullable();

            $table->timestamp('check_in_at')->nullable()->comment('UTC');
            $table->timestamp('check_out_at')->nullable()->comment('UTC');

            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('early_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);

            $table->boolean('is_late')->default(false);
            $table->boolean('is_early')->default(false);
            $table->boolean('overtime_approved')->default(false);

            $table->json('event_ids')->nullable()->comment('Raw event ids that produced this summary');
            $table->json('sources')->nullable()->comment('Which device(s), leave, holiday contributed');

            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'work_date'], 'as_employee_date_unique');
            $table->index(['organization_id', 'work_date'], 'as_org_date_idx');
            $table->index(['organization_id', 'employee_id', 'work_date'], 'as_org_emp_date_idx');
            $table->index(['organization_id', 'status'], 'as_org_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_summaries');
    }
};
