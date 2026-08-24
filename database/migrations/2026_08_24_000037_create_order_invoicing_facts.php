<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->unique(['administration_id', 'id', 'source_order_id'], 'sales_inv_tenant_id_source_unique');
        });

        Schema::create('order_invoice_draft_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->uuid('order_id');
            $table->uuid('sales_invoice_id');
            $table->timestamp('created_at');

            $table->unique(['administration_id', 'id'], 'oidr_tenant_id_unique');
            $table->unique(['administration_id', 'sales_invoice_id'], 'oidr_tenant_invoice_unique');
            $table->foreign(['administration_id', 'order_id'], 'oidr_order_tenant_fk')->references(['administration_id', 'id'])->on('orders')->restrictOnDelete();
            $table->foreign(['administration_id', 'sales_invoice_id', 'order_id'], 'oidr_invoice_source_fk')->references(['administration_id', 'id', 'source_order_id'])->on('sales_invoices')->restrictOnDelete();
        });

        Schema::create('order_invoice_reservations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->uuid('draft_request_id');
            $table->uuid('order_id');
            $table->uuid('order_line_id');
            $table->uuid('sales_invoice_id');
            $table->uuid('sales_invoice_line_id');
            $table->string('quantity', 64);
            $table->timestamp('created_at');

            $table->unique(['administration_id', 'id'], 'oir_tenant_id_unique');
            $table->unique(['administration_id', 'sales_invoice_id', 'sales_invoice_line_id'], 'oir_invoice_line_unique');
            $table->unique(['administration_id', 'draft_request_id', 'order_line_id'], 'oir_request_order_line_unique');
            $table->foreign(['administration_id', 'draft_request_id'], 'oir_request_tenant_fk')->references(['administration_id', 'id'])->on('order_invoice_draft_requests')->restrictOnDelete();
            $table->foreign(['administration_id', 'order_id', 'order_line_id'], 'oir_order_line_tenant_fk')->references(['administration_id', 'order_id', 'id'])->on('order_lines')->restrictOnDelete();
            $table->foreign(['administration_id', 'sales_invoice_id', 'sales_invoice_line_id'], 'oir_invoice_line_tenant_fk')->references(['administration_id', 'sales_invoice_id', 'id'])->on('sales_invoice_lines')->restrictOnDelete();
        });

        Schema::create('order_invoice_reservation_releases', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->uuid('reservation_id');
            $table->timestamp('created_at');

            $table->unique(['administration_id', 'id'], 'oirr_tenant_id_unique');
            $table->unique(['administration_id', 'reservation_id'], 'oirr_reservation_unique');
            $table->foreign(['administration_id', 'reservation_id'], 'oirr_reservation_tenant_fk')->references(['administration_id', 'id'])->on('order_invoice_reservations')->restrictOnDelete();
        });

        Schema::create('order_invoice_allocations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->uuid('reservation_id');
            $table->uuid('order_id');
            $table->uuid('order_line_id');
            $table->uuid('sales_invoice_id');
            $table->uuid('sales_invoice_line_id');
            $table->string('quantity', 64);
            $table->timestamp('created_at');

            $table->unique(['administration_id', 'id'], 'oia_tenant_id_unique');
            $table->unique(['administration_id', 'reservation_id'], 'oia_reservation_unique');
            $table->unique(['administration_id', 'sales_invoice_id', 'sales_invoice_line_id'], 'oia_invoice_line_unique');
            $table->foreign(['administration_id', 'reservation_id'], 'oia_reservation_tenant_fk')->references(['administration_id', 'id'])->on('order_invoice_reservations')->restrictOnDelete();
            $table->foreign(['administration_id', 'order_id', 'order_line_id'], 'oia_order_line_tenant_fk')->references(['administration_id', 'order_id', 'id'])->on('order_lines')->restrictOnDelete();
            $table->foreign(['administration_id', 'sales_invoice_id', 'sales_invoice_line_id'], 'oia_invoice_line_tenant_fk')->references(['administration_id', 'sales_invoice_id', 'id'])->on('sales_invoice_lines')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_invoice_allocations');
        Schema::dropIfExists('order_invoice_reservation_releases');
        Schema::dropIfExists('order_invoice_reservations');
        Schema::dropIfExists('order_invoice_draft_requests');
        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->dropUnique('sales_inv_tenant_id_source_unique');
        });
    }
};
