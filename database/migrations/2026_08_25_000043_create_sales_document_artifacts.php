<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_artifacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->enum('document_type', ['quotation', 'sales_invoice', 'sales_credit_invoice']);
            $table->unsignedInteger('version');
            $table->string('mime_type', 64);
            $table->string('filename', 255);
            $table->string('storage_key', 512)->unique();
            $table->char('sha256', 64);
            $table->unsignedBigInteger('byte_size');
            $table->timestamp('generated_at');
            $table->string('template_version', 64);
            $table->string('renderer_version', 128);
            $table->char('render_fingerprint', 64);
            $table->string('locale', 8);
            $table->timestamps();
            $table->foreign('administration_id')->references('id')->on('administrations')->restrictOnDelete();
            $table->unique(['administration_id', 'id'], 'doc_art_tenant_id_unique');
            $table->index(['administration_id', 'generated_at', 'id'], 'doc_art_tenant_generated_idx');
        });

        $this->link('quotation_document_artifacts', 'quotation_id', 'quotations', 'qda');
        $this->link('sales_invoice_document_artifacts', 'sales_invoice_id', 'sales_invoices', 'sida');
        $this->link('sales_credit_invoice_document_artifacts', 'sales_credit_invoice_id', 'sales_credit_invoices', 'scida');
    }

    private function link(string $tableName, string $sourceColumn, string $sourceTable, string $prefix): void
    {
        Schema::create($tableName, function (Blueprint $table) use ($sourceColumn, $sourceTable, $prefix): void {
            $table->uuid('artifact_id')->primary();
            $table->uuid('administration_id');
            $table->uuid($sourceColumn);
            $table->char('render_fingerprint', 64);
            $table->unsignedInteger('version');
            $table->timestamps();
            $table->foreign(['administration_id', 'artifact_id'], $prefix.'_artifact_tenant_fk')->references(['administration_id', 'id'])->on('document_artifacts')->restrictOnDelete();
            $table->foreign(['administration_id', $sourceColumn], $prefix.'_source_tenant_fk')->references(['administration_id', 'id'])->on($sourceTable)->restrictOnDelete();
            $table->unique(['administration_id', $sourceColumn, 'render_fingerprint'], $prefix.'_source_fingerprint_unique');
            $table->unique(['administration_id', $sourceColumn, 'version'], $prefix.'_source_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_credit_invoice_document_artifacts');
        Schema::dropIfExists('sales_invoice_document_artifacts');
        Schema::dropIfExists('quotation_document_artifacts');
        Schema::dropIfExists('document_artifacts');
    }
};
