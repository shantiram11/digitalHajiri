<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->string('fiscal_year', 16)->comment('e.g. 2082-83 BS or 2026-27 AD');
            $table->decimal('opening', 6, 2)->default(0);
            $table->decimal('accrued', 6, 2)->default(0);
            $table->decimal('consumed', 6, 2)->default(0);
            $table->decimal('adjusted', 6, 2)->default(0);
            $table->decimal('closing', 6, 2)->default(0);
            $table->timestamps();

            $table->unique(
                ['employee_id', 'leave_type_id', 'fiscal_year'],
                'lb_emp_type_year_unique'
            );
            $table->index(['organization_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_balances');
    }
};
