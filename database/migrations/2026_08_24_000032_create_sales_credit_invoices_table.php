<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_credit_invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->string('sales_credit_invoice_number', 32);
            $table->uuid('source_sales_invoice_id');
            $table->uuid('customer_id');
            $table->uuid('customer_relation_id_snapshot');
            $table->string('customer_number_snapshot', 32);
            $table->string('customer_name_snapshot', 255);
            $table->uuid('invoice_address_id_snapshot');
            $table->string('invoice_address_type_snapshot', 32);
            $table->string('invoice_address_line_1_snapshot', 255);
            $table->string('invoice_address_line_2_snapshot', 255)->nullable();
            $table->string('invoice_postal_code_snapshot', 32);
            $table->string('invoice_city_snapshot', 255);
            $table->char('invoice_country_code_snapshot', 2);
            $table->char('currency', 3);
            $table->date('credit_invoice_date');
            $table->enum('status', ['draft', 'finalized', 'posted', 'cancelled']);
            $table->timestamps();

            $table->foreign('administration_id')->references('id')->on('administrations')->restrictOnDelete();
            $table->foreign(['administration_id', 'source_sales_invoice_id'], 'sci_source_invoice_tenant_fk')->references(['administration_id', 'id'])->on('sales_invoices')->restrictOnDelete();
            $table->unique(['administration_id', 'id'], 'sci_tenant_id_unique');
            $table->unique(['administration_id', 'sales_credit_invoice_number'], 'sci_tenant_number_unique');
            $table->unique(['administration_id', 'source_sales_invoice_id'], 'sci_one_full_credit_unique');
            $table->index(['administration_id', 'status', 'credit_invoice_date', 'id'], 'sci_tenant_status_date_idx');
            $table->index(['administration_id', 'customer_id', 'credit_invoice_date', 'id'], 'sci_tenant_customer_date_idx');
        });

        Schema::create('sales_credit_invoice_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->uuid('sales_credit_invoice_id');
            $table->string('description', 1000);
            $table->string('quantity', 64);
            $table->text('unit_price_amount');
            $table->char('currency', 3);
            $table->timestamps();

            $table->foreign(['administration_id', 'sales_credit_invoice_id'], 'scil_credit_invoice_tenant_fk')->references(['administration_id', 'id'])->on('sales_credit_invoices')->restrictOnDelete();
            $table->unique(['administration_id', 'sales_credit_invoice_id', 'id'], 'scil_tenant_credit_id_unique');
            $table->index(['administration_id', 'sales_credit_invoice_id', 'id'], 'scil_tenant_credit_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_credit_invoice_lines');
        Schema::dropIfExists('sales_credit_invoices');
    }
};
