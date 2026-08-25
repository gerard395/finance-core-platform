<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('open_items', fn (Blueprint $table) => $table->date('due_date')->nullable()->after('opened_on'));
        Schema::create('purchase_invoice_postings', function (Blueprint $table): void {
            $table->uuid('administration_id');
            $table->uuid('purchase_invoice_id');
            $table->uuid('journal_entry_id');
            $table->uuid('open_item_id');
            $table->timestamp('created_at');
            $table->primary(['administration_id', 'purchase_invoice_id'], 'pip_primary');
            $table->unique(['administration_id', 'journal_entry_id'], 'pip_entry_unique');
            $table->unique(['administration_id', 'open_item_id'], 'pip_open_item_unique');
            $table->foreign(['administration_id', 'purchase_invoice_id'], 'pip_invoice_tenant_fk')->references(['administration_id', 'id'])->on('purchase_invoices')->restrictOnDelete();
            $table->foreign(['administration_id', 'journal_entry_id'], 'pip_entry_tenant_fk')->references(['administration_id', 'id'])->on('journal_entries')->restrictOnDelete();
            $table->foreign(['administration_id', 'open_item_id'], 'pip_open_item_tenant_fk')->references(['administration_id', 'id'])->on('open_items')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoice_postings');
        Schema::table('open_items', fn (Blueprint $table) => $table->dropColumn('due_date'));
    }
};
