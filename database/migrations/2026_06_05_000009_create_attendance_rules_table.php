<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attendance_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('name')->default('Default');

            $table->time('office_start_time');
            $table->time('office_end_time');

            $table->unsignedSmallInteger('grace_minutes')->default(10);
            $table->unsignedSmallInteger('early_grace_minutes')->default(10);
            $table->unsignedSmallInteger('overtime_threshold_minutes')->default(30)
                ->comment('Minutes past office_end_time before OT starts counting');
            $table->boolean('overtime_requires_approval')->default(true);

            $table->unsignedSmallInteger('full_day_threshold_minutes')->default(480);
            $table->unsignedSmallInteger('half_day_threshold_minutes')->default(240);
            $table->unsignedSmallInteger('absent_threshold_minutes')->default(60);

            $table->unsignedSmallInteger('duplicate_window_seconds')->default(60);
            $table->string('pairing_strategy', 32)->default('first_in_last_out')
                ->comment('first_in_last_out|pair_in_out');

            $table->json('weekly_off_days')->nullable()->comment('JSON array of weekday names');

            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('extras')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
            $table->index(['organization_id', 'employee_id']);
            $table->index(['organization_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_rules');
    }
};
