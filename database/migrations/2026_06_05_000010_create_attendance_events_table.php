<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Raw attendance events — append-only.
     * Never soft-deleted, never edited after insert.
     */
    public function up(): void
    {
        Schema::create('attendance_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()
                ->comment('Null = orphan event, awaiting mapping')
                ->constrained()->nullOnDelete();
            $table->foreignId('device_id')->nullable()
                ->comment('Null when source is mobile/manual/web')
                ->constrained()->nullOnDelete();

            $table->string('device_user_id', 64)->nullable()
                ->comment('Raw vendor-side user ID');

            $table->timestamp('event_timestamp')->comment('UTC');
            $table->string('verification_method', 32)
                ->comment('fingerprint|face|card|password|manual|mobile|web');
            $table->string('source', 32)->default('device')
                ->comment('device|manual|correction|mobile|api');

            $table->unsignedBigInteger('device_log_id')->nullable()
                ->comment('Vendor cursor for dedupe');

            $table->json('raw_payload')->nullable();
            $table->timestamp('ingested_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['device_id', 'device_user_id', 'event_timestamp', 'verification_method'],
                'attendance_events_natural_key_unique'
            );

            $table->index(['organization_id', 'employee_id', 'event_timestamp'], 'ae_org_emp_ts_idx');
            $table->index(['device_id', 'event_timestamp'], 'ae_device_ts_idx');
            $table->index('event_timestamp');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_events');
    }
};
