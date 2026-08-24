<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_credit_invoice_postings', function (Blueprint $table): void {
            $table->uuid('administration_id');
            $table->uuid('sales_credit_invoice_id');
            $table->uuid('journal_entry_id');
            $table->uuid('open_item_id');
            $table->timestamp('created_at');

            $table->primary(['administration_id', 'sales_credit_invoice_id'], 'scip_primary');
            $table->unique(['administration_id', 'journal_entry_id'], 'scip_entry_unique');
            $table->unique(['administration_id', 'open_item_id'], 'scip_open_item_unique');
            $table->foreign(['administration_id', 'sales_credit_invoice_id'], 'scip_credit_tenant_fk')->references(['administration_id', 'id'])->on('sales_credit_invoices')->restrictOnDelete();
            $table->foreign(['administration_id', 'journal_entry_id'], 'scip_entry_tenant_fk')->references(['administration_id', 'id'])->on('journal_entries')->restrictOnDelete();
            $table->foreign(['administration_id', 'open_item_id'], 'scip_open_item_tenant_fk')->references(['administration_id', 'id'])->on('open_items')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_credit_invoice_postings');
    }
};
