<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_document_delivery_attempts', function (Blueprint $table): void {
            $table->timestamp('transport_started_at')->nullable()->after('started_at');
        });

        Schema::create('delivery_worker_heartbeats', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('capability', 64);
            $table->string('queue_name', 128);
            $table->string('worker_identity', 128);
            $table->string('release', 128)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('last_seen_at');
            $table->timestamps();
            $table->unique(['capability', 'queue_name'], 'delivery_heartbeat_capability_queue_unique');
        });

        Schema::create('sales_document_delivery_outcome_resolutions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->uuid('delivery_request_id');
            $table->uuid('delivery_attempt_id');
            $table->enum('resolution_type', ['handled_externally', 'authorize_resend']);
            $table->uuid('resolved_by');
            $table->timestamp('resolved_at');
            $table->string('reason', 500)->nullable();
            $table->timestamps();
            $table->foreign(['administration_id', 'delivery_request_id'], 'sddor_request_tenant_fk')->references(['administration_id', 'id'])->on('sales_document_delivery_requests')->restrictOnDelete();
            $table->foreign('delivery_attempt_id', 'sddor_attempt_fk')->references('id')->on('sales_document_delivery_attempts')->restrictOnDelete();
            $table->foreign('resolved_by', 'sddor_actor_fk')->references('id')->on('domain_users')->restrictOnDelete();
            $table->unique('delivery_attempt_id', 'sddor_attempt_unique');
            $table->index(['administration_id', 'delivery_request_id'], 'sddor_request_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_document_delivery_outcome_resolutions');
        Schema::dropIfExists('delivery_worker_heartbeats');
        Schema::table('sales_document_delivery_attempts', fn (Blueprint $table) => $table->dropColumn('transport_started_at'));
    }
};
