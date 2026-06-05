<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');
            $table->string('vendor', 64)->comment('zkteco|hikvision|fake|...');
            $table->string('model', 128)->nullable();
            $table->string('serial', 128)->nullable();
            $table->string('firmware', 64)->nullable();

            $table->string('location')->nullable();
            $table->string('ip', 64)->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('communication_key', 128)->nullable()->comment('Vendor-specific auth secret');
            $table->json('settings')->nullable();

            $table->string('timezone', 64)->default('Asia/Kathmandu');
            $table->timestamp('device_time')->nullable();
            $table->timestamp('server_time')->nullable();
            $table->integer('clock_drift_seconds')->nullable();

            $table->timestamp('last_online_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->unsignedBigInteger('last_log_id')->nullable()->comment('Vendor cursor');

            $table->string('status', 32)->default('active')
                ->comment('active|offline|error|disabled');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'serial']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
