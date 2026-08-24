<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_invoice_postings', function (Blueprint $table): void {
            $table->uuid('administration_id');
            $table->uuid('sales_invoice_id');
            $table->uuid('journal_entry_id');
            $table->uuid('open_item_id');
            $table->timestamp('created_at');

            $table->primary(['administration_id', 'sales_invoice_id'], 'sip_primary');
            $table->unique(['administration_id', 'journal_entry_id'], 'sip_entry_unique');
            $table->unique(['administration_id', 'open_item_id'], 'sip_open_item_unique');
            $table->foreign(['administration_id', 'sales_invoice_id'], 'sip_invoice_tenant_fk')->references(['administration_id', 'id'])->on('sales_invoices')->restrictOnDelete();
            $table->foreign(['administration_id', 'journal_entry_id'], 'sip_entry_tenant_fk')->references(['administration_id', 'id'])->on('journal_entries')->restrictOnDelete();
            $table->foreign(['administration_id', 'open_item_id'], 'sip_open_item_tenant_fk')->references(['administration_id', 'id'])->on('open_items')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_invoice_postings');
    }
};
