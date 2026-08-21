<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->string('order_number', 32);
            $table->uuid('customer_id');
            $table->uuid('customer_relation_id_snapshot');
            $table->string('customer_number_snapshot', 32);
            $table->string('customer_name_snapshot', 255);
            $table->uuid('source_quotation_id')->nullable();
            $table->char('currency', 3);
            $table->date('order_date');
            $table->enum('status', ['draft', 'confirmed', 'partially_invoiced', 'fully_invoiced', 'cancelled']);
            $table->timestamps();

            $table->foreign('administration_id')->references('id')->on('administrations')->restrictOnDelete();
            $table->foreign(['administration_id', 'customer_id'], 'orders_customer_tenant_fk')->references(['administration_id', 'id'])->on('customers')->restrictOnDelete();
            $table->foreign(['administration_id', 'source_quotation_id'], 'orders_source_quotation_tenant_fk')->references(['administration_id', 'id'])->on('quotations')->restrictOnDelete();
            $table->unique(['administration_id', 'id']);
            $table->unique(['administration_id', 'order_number']);
            $table->index(['administration_id', 'status', 'order_date', 'id']);
            $table->index(['administration_id', 'customer_id', 'order_date', 'id']);
        });

        Schema::create('order_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->uuid('order_id');
            $table->string('description', 1000);
            $table->string('quantity', 64);
            $table->text('unit_price_amount');
            $table->char('currency', 3);
            $table->timestamps();

            $table->foreign(['administration_id', 'order_id'], 'order_lines_order_tenant_fk')->references(['administration_id', 'id'])->on('orders')->restrictOnDelete();
            $table->unique(['administration_id', 'order_id', 'id'], 'order_lines_tenant_order_id_unique');
            $table->index(['administration_id', 'order_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_lines');
        Schema::dropIfExists('orders');
    }
};
