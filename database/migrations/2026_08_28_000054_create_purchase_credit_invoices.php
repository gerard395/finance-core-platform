<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_invoice_lines', fn (Blueprint $table) => $table->unique(['administration_id', 'purchase_invoice_id', 'id'], 'pil_tenant_invoice_id_unique'));

        Schema::create('purchase_credit_invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->uuid('supplier_id');
            $table->uuid('supplier_relation_id_snapshot');
            $table->string('supplier_number_snapshot', 32);
            $table->string('supplier_name_snapshot');
            $table->string('supplier_vat_id_snapshot', 32)->nullable();
            $table->char('supplier_jurisdiction_snapshot', 2)->nullable();
            $table->uuid('source_purchase_invoice_id');
            $table->uuid('source_payable_open_item_id');
            $table->string('supplier_credit_invoice_number', 512)->collation('utf8mb4_bin');
            $table->date('supplier_credit_date');
            $table->date('received_date');
            $table->date('fiscal_reporting_date');
            $table->date('source_supply_date')->nullable();
            $table->char('currency', 3);
            $table->string('address_line_1_snapshot');
            $table->string('address_line_2_snapshot')->nullable();
            $table->string('postal_code_snapshot', 32);
            $table->string('city_snapshot');
            $table->char('country_code_snapshot', 2);
            $table->string('status', 20);
            $table->uuid('created_by');
            $table->timestamp('created_at');
            $table->uuid('finalized_by')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->uuid('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('updated_at');
            $table->unique(['administration_id', 'id']);
            $table->unique(['administration_id', 'supplier_id', 'supplier_credit_invoice_number'], 'pci_supplier_number_unique');
            $table->foreign('administration_id', 'pci_admin_fk')->references('id')->on('administrations')->restrictOnDelete();
            $table->foreign(['administration_id', 'supplier_id'], 'pci_supplier_fk')->references(['administration_id', 'id'])->on('suppliers')->restrictOnDelete();
            $table->foreign(['administration_id', 'supplier_relation_id_snapshot'], 'pci_relation_fk')->references(['administration_id', 'id'])->on('relations')->restrictOnDelete();
            $table->foreign(['administration_id', 'source_purchase_invoice_id'], 'pci_source_fk')->references(['administration_id', 'id'])->on('purchase_invoices')->restrictOnDelete();
            $table->foreign(['administration_id', 'source_payable_open_item_id'], 'pci_payable_fk')->references(['administration_id', 'id'])->on('open_items')->restrictOnDelete();
            $table->foreign('created_by', 'pci_created_by_fk')->references('id')->on('domain_users')->restrictOnDelete();
            $table->foreign('finalized_by', 'pci_finalized_by_fk')->references('id')->on('domain_users')->restrictOnDelete();
            $table->foreign('cancelled_by', 'pci_cancelled_by_fk')->references('id')->on('domain_users')->restrictOnDelete();
        });

        Schema::create('purchase_credit_invoice_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->uuid('purchase_credit_invoice_id');
            $table->uuid('source_purchase_invoice_id');
            $table->uuid('source_purchase_invoice_line_id');
            $table->uuid('source_tax_posting_id')->nullable();
            $table->text('description');
            $table->string('quantity', 32);
            $table->string('unit_price_amount', 40);
            $table->char('currency', 3);
            $table->uuid('ledger_account_id');
            $table->string('ledger_account_code_snapshot', 32);
            $table->string('ledger_account_name_snapshot');
            $table->string('ledger_account_type_snapshot', 20);
            $table->uuid('tax_code_id');
            $table->string('tax_code_snapshot', 16);
            $table->string('tax_name_snapshot');
            $table->string('tax_rate_snapshot', 8);
            $table->string('tax_direction_snapshot', 10);
            $table->string('tax_treatment_snapshot', 40);
            $table->string('vat_return_classification_snapshot', 40);
            $table->string('icp_classification_snapshot', 20);
            $table->string('net_amount', 40);
            $table->string('taxable_base', 40);
            $table->string('tax_amount', 40);
            $table->string('gross_amount', 40);
            $table->timestamps();
            $table->unique(['administration_id', 'purchase_credit_invoice_id', 'source_purchase_invoice_line_id'], 'pcil_source_once');
            $table->foreign(['administration_id', 'purchase_credit_invoice_id'], 'pcil_credit_fk')->references(['administration_id', 'id'])->on('purchase_credit_invoices')->restrictOnDelete();
            $table->foreign(['administration_id', 'source_purchase_invoice_id', 'source_purchase_invoice_line_id'], 'pcil_source_line_fk')->references(['administration_id', 'purchase_invoice_id', 'id'])->on('purchase_invoice_lines')->restrictOnDelete();
            $table->foreign(['administration_id', 'source_tax_posting_id'], 'pcil_tax_posting_fk')->references(['administration_id', 'id'])->on('tax_postings')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE purchase_credit_invoices ADD CONSTRAINT pci_status_check CHECK (status IN ('draft','finalized','posted','cancelled')), ADD CONSTRAINT pci_currency_check CHECK (currency = 'EUR'), ADD CONSTRAINT pci_dates_check CHECK (fiscal_reporting_date = GREATEST(supplier_credit_date, received_date)), ADD CONSTRAINT pci_audit_check CHECK ((status='draft' AND finalized_by IS NULL AND finalized_at IS NULL AND cancelled_by IS NULL AND cancelled_at IS NULL) OR (status='finalized' AND finalized_by IS NOT NULL AND finalized_at IS NOT NULL AND cancelled_by IS NULL AND cancelled_at IS NULL) OR (status='posted' AND finalized_by IS NOT NULL AND finalized_at IS NOT NULL AND cancelled_by IS NULL AND cancelled_at IS NULL) OR (status='cancelled' AND cancelled_by IS NOT NULL AND cancelled_at IS NOT NULL))");
        DB::statement("ALTER TABLE purchase_credit_invoice_lines ADD CONSTRAINT pcil_direction_check CHECK (tax_direction_snapshot='input'), ADD CONSTRAINT pcil_account_type_check CHECK (ledger_account_type_snapshot IN ('expense','asset'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_credit_invoice_lines');
        Schema::dropIfExists('purchase_credit_invoices');
        Schema::table('purchase_invoice_lines', fn (Blueprint $table) => $table->dropUnique('pil_tenant_invoice_id_unique'));
    }
};
