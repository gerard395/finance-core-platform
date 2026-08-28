<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_invoice_lines', fn (Blueprint $table) => $table->unique(['administration_id', 'id'], 'pil_tenant_id_unique'));
        Schema::table('purchase_credit_invoice_lines', fn (Blueprint $table) => $table->unique(['administration_id', 'purchase_credit_invoice_id', 'id'], 'pcil_tenant_credit_id_unique'));
        DB::statement('ALTER TABLE purchase_credit_invoices DROP CHECK pci_audit_check');
        Schema::table('purchase_credit_invoices', function (Blueprint $table): void {
            $table->uuid('posted_by')->nullable()->after('finalized_at');
            $table->timestamp('posted_at')->nullable()->after('posted_by');
            $table->foreign('posted_by', 'pci_posted_by_fk')->references('id')->on('domain_users')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE purchase_credit_invoices ADD CONSTRAINT pci_audit_check CHECK ((status='draft' AND finalized_by IS NULL AND finalized_at IS NULL AND posted_by IS NULL AND posted_at IS NULL AND cancelled_by IS NULL AND cancelled_at IS NULL) OR (status='finalized' AND finalized_by IS NOT NULL AND finalized_at IS NOT NULL AND posted_by IS NULL AND posted_at IS NULL AND cancelled_by IS NULL AND cancelled_at IS NULL) OR (status='posted' AND finalized_by IS NOT NULL AND finalized_at IS NOT NULL AND posted_by IS NOT NULL AND posted_at IS NOT NULL AND cancelled_by IS NULL AND cancelled_at IS NULL) OR (status='cancelled' AND posted_by IS NULL AND posted_at IS NULL AND cancelled_by IS NOT NULL AND cancelled_at IS NOT NULL))");

        Schema::create('purchase_credit_source_line_claims', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->uuid('source_purchase_invoice_line_id');
            $table->uuid('purchase_credit_invoice_id');
            $table->uuid('purchase_credit_invoice_line_id');
            $table->timestamp('created_at');
            $table->unique(['administration_id', 'id']);
            $table->unique(['administration_id', 'source_purchase_invoice_line_id'], 'pcslc_source_unique');
            $table->unique(['administration_id', 'purchase_credit_invoice_id', 'purchase_credit_invoice_line_id'], 'pcslc_credit_line_unique');
            $table->foreign(['administration_id', 'purchase_credit_invoice_id'], 'pcslc_credit_fk')->references(['administration_id', 'id'])->on('purchase_credit_invoices')->restrictOnDelete();
            $table->foreign(['administration_id', 'purchase_credit_invoice_id', 'purchase_credit_invoice_line_id'], 'pcslc_line_fk')->references(['administration_id', 'purchase_credit_invoice_id', 'id'])->on('purchase_credit_invoice_lines')->restrictOnDelete();
            $table->foreign(['administration_id', 'source_purchase_invoice_line_id'], 'pcslc_source_fk')->references(['administration_id', 'id'])->on('purchase_invoice_lines')->restrictOnDelete();
        });

        Schema::create('purchase_credit_invoice_postings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->uuid('purchase_credit_invoice_id');
            $table->uuid('journal_entry_id');
            $table->uuid('open_item_id');
            $table->date('posting_date');
            $table->timestamp('created_at');
            $table->unique(['administration_id', 'id']);
            $table->unique(['administration_id', 'purchase_credit_invoice_id'], 'pcip_credit_unique');
            $table->unique(['administration_id', 'journal_entry_id'], 'pcip_entry_unique');
            $table->unique(['administration_id', 'open_item_id'], 'pcip_open_item_unique');
            $table->foreign(['administration_id', 'purchase_credit_invoice_id'], 'pcip_credit_fk')->references(['administration_id', 'id'])->on('purchase_credit_invoices')->restrictOnDelete();
            $table->foreign(['administration_id', 'journal_entry_id'], 'pcip_entry_fk')->references(['administration_id', 'id'])->on('journal_entries')->restrictOnDelete();
            $table->foreign(['administration_id', 'open_item_id'], 'pcip_open_item_fk')->references(['administration_id', 'id'])->on('open_items')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_credit_invoice_postings');
        Schema::dropIfExists('purchase_credit_source_line_claims');
        DB::statement('ALTER TABLE purchase_credit_invoices DROP CHECK pci_audit_check');
        Schema::table('purchase_credit_invoices', function (Blueprint $table): void {
            $table->dropForeign('pci_posted_by_fk');
            $table->dropColumn(['posted_by', 'posted_at']);
        });
        DB::statement("ALTER TABLE purchase_credit_invoices ADD CONSTRAINT pci_audit_check CHECK ((status='draft' AND finalized_by IS NULL AND finalized_at IS NULL AND cancelled_by IS NULL AND cancelled_at IS NULL) OR (status='finalized' AND finalized_by IS NOT NULL AND finalized_at IS NOT NULL AND cancelled_by IS NULL AND cancelled_at IS NULL) OR (status='posted' AND finalized_by IS NOT NULL AND finalized_at IS NOT NULL AND cancelled_by IS NULL AND cancelled_at IS NULL) OR (status='cancelled' AND cancelled_by IS NOT NULL AND cancelled_at IS NOT NULL))");
        Schema::table('purchase_credit_invoice_lines', fn (Blueprint $table) => $table->dropUnique('pcil_tenant_credit_id_unique'));
        Schema::table('purchase_invoice_lines', fn (Blueprint $table) => $table->dropUnique('pil_tenant_id_unique'));
    }
};
