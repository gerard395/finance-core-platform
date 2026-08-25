<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_document_artifacts', fn (Blueprint $table) => $table->unique(['administration_id', 'artifact_id', 'quotation_id'], 'qda_delivery_source_unique'));
        Schema::table('sales_invoice_document_artifacts', fn (Blueprint $table) => $table->unique(['administration_id', 'artifact_id', 'sales_invoice_id'], 'sida_delivery_source_unique'));
        Schema::table('sales_credit_invoice_document_artifacts', fn (Blueprint $table) => $table->unique(['administration_id', 'artifact_id', 'sales_credit_invoice_id'], 'scida_delivery_source_unique'));

        Schema::create('sales_document_delivery_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->enum('document_type', ['quotation', 'sales_invoice', 'sales_credit_invoice']);
            $table->uuid('quotation_id')->nullable();
            $table->uuid('sales_invoice_id')->nullable();
            $table->uuid('sales_credit_invoice_id')->nullable();
            $table->uuid('artifact_id');
            $table->string('recipient_email', 254);
            $table->string('recipient_name');
            $table->uuid('recipient_contact_id')->nullable();
            $table->string('recipient_source', 32);
            $table->string('from_name');
            $table->string('from_email', 254);
            $table->string('reply_to', 254)->nullable();
            $table->string('subject');
            $table->text('body');
            $table->string('template_version', 64);
            $table->char('semantic_fingerprint', 64);
            $table->uuid('initiated_by');
            $table->enum('status', ['requested', 'prepared', 'attempting', 'accepted_by_transport', 'failed', 'outcome_unknown']);
            $table->timestamp('requested_at');
            $table->timestamps();
            $table->foreign('administration_id')->references('id')->on('administrations')->restrictOnDelete();
            $table->foreign(['administration_id', 'artifact_id'], 'sddr_artifact_tenant_fk')->references(['administration_id', 'id'])->on('document_artifacts')->restrictOnDelete();
            $table->foreign(['administration_id', 'quotation_id'], 'sddr_quotation_tenant_fk')->references(['administration_id', 'id'])->on('quotations')->restrictOnDelete();
            $table->foreign(['administration_id', 'sales_invoice_id'], 'sddr_invoice_tenant_fk')->references(['administration_id', 'id'])->on('sales_invoices')->restrictOnDelete();
            $table->foreign(['administration_id', 'sales_credit_invoice_id'], 'sddr_credit_tenant_fk')->references(['administration_id', 'id'])->on('sales_credit_invoices')->restrictOnDelete();
            $table->foreign(['administration_id', 'artifact_id', 'quotation_id'], 'sddr_quotation_artifact_fk')->references(['administration_id', 'artifact_id', 'quotation_id'])->on('quotation_document_artifacts')->restrictOnDelete();
            $table->foreign(['administration_id', 'artifact_id', 'sales_invoice_id'], 'sddr_invoice_artifact_fk')->references(['administration_id', 'artifact_id', 'sales_invoice_id'])->on('sales_invoice_document_artifacts')->restrictOnDelete();
            $table->foreign(['administration_id', 'artifact_id', 'sales_credit_invoice_id'], 'sddr_credit_artifact_fk')->references(['administration_id', 'artifact_id', 'sales_credit_invoice_id'])->on('sales_credit_invoice_document_artifacts')->restrictOnDelete();
            $table->foreign('initiated_by')->references('id')->on('domain_users')->restrictOnDelete();
            $table->unique(['administration_id', 'id'], 'sddr_tenant_id_unique');
            $table->index(['administration_id', 'document_type', 'quotation_id'], 'sddr_quotation_history_idx');
            $table->index(['administration_id', 'document_type', 'sales_invoice_id'], 'sddr_invoice_history_idx');
            $table->index(['administration_id', 'document_type', 'sales_credit_invoice_id'], 'sddr_credit_history_idx');
        });
        DB::statement("ALTER TABLE sales_document_delivery_requests ADD CONSTRAINT sddr_typed_source_check CHECK ((document_type = 'quotation' and quotation_id is not null and sales_invoice_id is null and sales_credit_invoice_id is null) or (document_type = 'sales_invoice' and quotation_id is null and sales_invoice_id is not null and sales_credit_invoice_id is null) or (document_type = 'sales_credit_invoice' and quotation_id is null and sales_invoice_id is null and sales_credit_invoice_id is not null))");

        Schema::create('sales_document_delivery_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->uuid('delivery_request_id');
            $table->unsignedSmallInteger('attempt_number');
            $table->uuid('artifact_id');
            $table->enum('result', ['attempting', 'accepted_by_transport', 'failed_configuration', 'failed_validation', 'failed_transport', 'outcome_unknown']);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->string('transport', 128)->nullable();
            $table->string('external_message_id')->nullable();
            $table->string('error_category', 64)->nullable();
            $table->boolean('retryable')->default(false);
            $table->timestamps();
            $table->foreign(['administration_id', 'delivery_request_id'], 'sdda_request_tenant_fk')->references(['administration_id', 'id'])->on('sales_document_delivery_requests')->restrictOnDelete();
            $table->foreign(['administration_id', 'artifact_id'], 'sdda_artifact_tenant_fk')->references(['administration_id', 'id'])->on('document_artifacts')->restrictOnDelete();
            $table->unique(['administration_id', 'delivery_request_id', 'attempt_number'], 'sdda_request_attempt_unique');
            $table->index(['administration_id', 'delivery_request_id', 'started_at'], 'sdda_request_history_idx');
        });

        Schema::create('sales_document_delivery_outbox', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->uuid('delivery_request_id');
            $table->string('intent_type', 32)->default('initial_delivery');
            $table->enum('status', ['available', 'processing', 'processed', 'blocked']);
            $table->timestamp('available_at');
            $table->unsignedSmallInteger('processing_attempts')->default(0);
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('lease_expires_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->foreign(['administration_id', 'delivery_request_id'], 'sddo_request_tenant_fk')->references(['administration_id', 'id'])->on('sales_document_delivery_requests')->restrictOnDelete();
            $table->unique(['administration_id', 'delivery_request_id', 'intent_type'], 'sddo_request_intent_unique');
            $table->index(['status', 'available_at'], 'sddo_available_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_document_delivery_outbox');
        Schema::dropIfExists('sales_document_delivery_attempts');
        Schema::dropIfExists('sales_document_delivery_requests');
        Schema::table('quotation_document_artifacts', fn (Blueprint $table) => $table->dropUnique('qda_delivery_source_unique'));
        Schema::table('sales_invoice_document_artifacts', fn (Blueprint $table) => $table->dropUnique('sida_delivery_source_unique'));
        Schema::table('sales_credit_invoice_document_artifacts', fn (Blueprint $table) => $table->dropUnique('scida_delivery_source_unique'));
    }
};
