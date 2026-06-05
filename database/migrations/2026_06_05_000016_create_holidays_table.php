<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')
                ->nullable()
                ->comment('Null = national/global holiday')
                ->constrained()->cascadeOnDelete();
            $table->date('date')->comment('AD');
            $table->string('name');
            $table->string('type', 32)->default('public')
                ->comment('public|regional|organization|optional');
            $table->boolean('is_recurring')->default(false);
            $table->boolean('is_paid')->default(true);
            $table->string('fiscal_year', 16)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
