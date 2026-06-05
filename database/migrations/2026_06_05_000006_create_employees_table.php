<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('employee_code', 64);
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->date('date_of_birth')->nullable()->comment('AD');
            $table->string('gender', 16)->nullable();
            $table->string('citizenship_no', 64)->nullable();
            $table->string('employment_type', 32)->default('permanent')
                ->comment('permanent|contract|temporary|intern');
            $table->date('joined_at')->nullable()->comment('AD');
            $table->date('resigned_at')->nullable();
            $table->string('status', 32)->default('active')
                ->comment('active|suspended|resigned|retired');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'employee_code']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'department_id']);
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('head_employee_id')->references('id')->on('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['head_employee_id']);
        });
        Schema::dropIfExists('employees');
    }
};
