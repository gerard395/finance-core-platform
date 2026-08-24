<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->string('sales_invoice_number', 32);
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
            $table->uuid('source_order_id')->nullable();
            $table->char('currency', 3);
            $table->date('invoice_date');
            $table->date('due_date');
            $table->enum('status', ['draft', 'finalized', 'posted', 'paid', 'cancelled']);
            $table->timestamps();

            $table->foreign('administration_id')->references('id')->on('administrations')->restrictOnDelete();
            $table->foreign(['administration_id', 'customer_id'], 'sales_invoices_customer_tenant_fk')->references(['administration_id', 'id'])->on('customers')->restrictOnDelete();
            $table->foreign(['administration_id', 'source_order_id'], 'sales_invoices_source_order_tenant_fk')->references(['administration_id', 'id'])->on('orders')->restrictOnDelete();
            $table->unique(['administration_id', 'id']);
            $table->unique(['administration_id', 'sales_invoice_number']);
            $table->index(['administration_id', 'status', 'invoice_date', 'id']);
            $table->index(['administration_id', 'customer_id', 'invoice_date', 'id'], 'sales_invoices_tenant_customer_date_idx');
        });

        Schema::create('sales_invoice_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->uuid('sales_invoice_id');
            $table->string('description', 1000);
            $table->string('quantity', 64);
            $table->text('unit_price_amount');
            $table->char('currency', 3);
            $table->uuid('tax_code_id_snapshot');
            $table->string('tax_code_snapshot', 32);
            $table->string('tax_name_snapshot', 255);
            $table->string('tax_rate_snapshot', 16);
            $table->string('tax_direction_snapshot', 16);
            $table->timestamps();

            $table->foreign(['administration_id', 'sales_invoice_id'], 'sales_invoice_lines_invoice_tenant_fk')->references(['administration_id', 'id'])->on('sales_invoices')->restrictOnDelete();
            $table->foreign(['administration_id', 'tax_code_id_snapshot'], 'sales_invoice_lines_tax_code_tenant_fk')->references(['administration_id', 'id'])->on('tax_codes')->restrictOnDelete();
            $table->unique(['administration_id', 'sales_invoice_id', 'id'], 'sales_invoice_lines_tenant_invoice_id_unique');
            $table->index(['administration_id', 'sales_invoice_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_invoice_lines');
        Schema::dropIfExists('sales_invoices');
    }
};
