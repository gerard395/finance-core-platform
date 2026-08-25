<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', fn (Blueprint $table) => $table->unique(['administration_id', 'id'], 'suppliers_admin_id_unique'));
        Schema::create('purchase_invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->uuid('supplier_id');
            $table->uuid('supplier_relation_id_snapshot');
            $table->string('supplier_number_snapshot', 32);
            $table->string('supplier_name_snapshot');
            $table->string('supplier_vat_id_snapshot', 32)->nullable();
            $table->char('supplier_jurisdiction_snapshot', 2)->nullable();
            $table->string('supplier_invoice_number', 512)->collation('utf8mb4_bin');
            $table->date('supplier_invoice_date');
            $table->date('received_date');
            $table->date('supply_date')->nullable();
            $table->date('fiscal_reporting_date');
            $table->date('due_date');
            $table->char('currency', 3);
            $table->string('address_line_1_snapshot');
            $table->string('address_line_2_snapshot')->nullable();
            $table->string('postal_code_snapshot', 32);
            $table->string('city_snapshot');
            $table->char('country_code_snapshot', 2);
            $table->string('status', 20);
            $table->uuid('finalized_by')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
            $table->unique(['administration_id', 'id']);
            $table->unique(['administration_id', 'supplier_id', 'supplier_invoice_number'], 'pi_supplier_number_unique');
            $table->foreign('administration_id', 'pi_admin_fk')->references('id')->on('administrations')->restrictOnDelete();
            $table->foreign(['administration_id', 'supplier_id'], 'pi_supplier_tenant_fk')->references(['administration_id', 'id'])->on('suppliers')->restrictOnDelete();
            $table->foreign(['administration_id', 'supplier_relation_id_snapshot'], 'pi_relation_tenant_fk')->references(['administration_id', 'id'])->on('relations')->restrictOnDelete();
            $table->foreign('finalized_by', 'pi_finalized_by_fk')->references('id')->on('domain_users')->restrictOnDelete();
            $table->index(['administration_id', 'status', 'supplier_invoice_date'], 'pi_tenant_status_date_idx');
        });
        Schema::create('purchase_invoice_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->uuid('purchase_invoice_id');
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
            $table->string('tax_amount', 40);
            $table->string('gross_amount', 40);
            $table->timestamps();
            $table->foreign(['administration_id', 'purchase_invoice_id'], 'pil_invoice_tenant_fk')->references(['administration_id', 'id'])->on('purchase_invoices')->restrictOnDelete();
            $table->foreign(['administration_id', 'ledger_account_id'], 'pil_account_tenant_fk')->references(['administration_id', 'id'])->on('ledger_accounts')->restrictOnDelete();
            $table->foreign(['administration_id', 'tax_code_id'], 'pil_tax_tenant_fk')->references(['administration_id', 'id'])->on('tax_codes')->restrictOnDelete();
            $table->index(['administration_id', 'purchase_invoice_id', 'id'], 'pil_invoice_order_idx');
        });
        DB::statement("ALTER TABLE purchase_invoices ADD CONSTRAINT pi_status_check CHECK (status IN ('draft','finalized','posted','cancelled')), ADD CONSTRAINT pi_currency_check CHECK (currency = 'EUR'), ADD CONSTRAINT pi_dates_check CHECK (due_date >= supplier_invoice_date AND fiscal_reporting_date = GREATEST(supplier_invoice_date, received_date)), ADD CONSTRAINT pi_finalize_check CHECK ((status = 'draft' AND finalized_by IS NULL AND finalized_at IS NULL) OR (status = 'cancelled') OR (status IN ('finalized','posted') AND finalized_by IS NOT NULL AND finalized_at IS NOT NULL))");
        DB::statement("ALTER TABLE purchase_invoice_lines ADD CONSTRAINT pil_currency_check CHECK (currency = 'EUR'), ADD CONSTRAINT pil_account_type_check CHECK (ledger_account_type_snapshot IN ('expense','asset')), ADD CONSTRAINT pil_direction_check CHECK (tax_direction_snapshot = 'input')");
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoice_lines');
        Schema::dropIfExists('purchase_invoices');
        Schema::table('suppliers', fn (Blueprint $table) => $table->dropUnique('suppliers_admin_id_unique'));
    }
};
