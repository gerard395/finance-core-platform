<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->string('customer_vat_id_snapshot', 32)->nullable()->after('invoice_country_code_snapshot');
            $table->char('customer_fiscal_jurisdiction_snapshot', 2)->nullable()->after('customer_vat_id_snapshot');
            $table->string('supplier_vat_id_snapshot', 32)->nullable()->after('customer_fiscal_jurisdiction_snapshot');
            $table->char('supplier_fiscal_jurisdiction_snapshot', 2)->nullable()->after('supplier_vat_id_snapshot');
            $table->date('supply_date')->nullable()->after('supplier_fiscal_jurisdiction_snapshot');
        });

        Schema::table('sales_credit_invoices', function (Blueprint $table): void {
            $table->string('customer_vat_id_snapshot', 32)->nullable()->after('invoice_country_code_snapshot');
            $table->char('customer_fiscal_jurisdiction_snapshot', 2)->nullable()->after('customer_vat_id_snapshot');
            $table->string('supplier_vat_id_snapshot', 32)->nullable()->after('customer_fiscal_jurisdiction_snapshot');
            $table->char('supplier_fiscal_jurisdiction_snapshot', 2)->nullable()->after('supplier_vat_id_snapshot');
            $table->date('original_supply_date')->nullable()->after('supplier_fiscal_jurisdiction_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('sales_credit_invoices', function (Blueprint $table): void {
            $table->dropColumn(['customer_vat_id_snapshot', 'customer_fiscal_jurisdiction_snapshot', 'supplier_vat_id_snapshot', 'supplier_fiscal_jurisdiction_snapshot', 'original_supply_date']);
        });
        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->dropColumn(['customer_vat_id_snapshot', 'customer_fiscal_jurisdiction_snapshot', 'supplier_vat_id_snapshot', 'supplier_fiscal_jurisdiction_snapshot', 'supply_date']);
        });
    }
};
