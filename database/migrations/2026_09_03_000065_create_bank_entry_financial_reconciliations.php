<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_entry_reconciliations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->uuid('bank_statement_entry_id');
            $table->uuid('bank_transaction_id');
            $table->enum('intent', ['customer_receipt', 'supplier_payment', 'other']);
            $table->date('booking_date');
            $table->date('posting_date');
            $table->uuid('actor_id');
            $table->timestamp('occurred_at', 6);
            $table->uuid('replaces_reconciliation_id')->nullable();
            $table->unique(['administration_id', 'id']);
            $table->unique(['administration_id', 'bank_statement_entry_id', 'id'], 'ber_tenant_entry_id_unique');
            $table->unique(['administration_id', 'bank_transaction_id'], 'ber_transaction_unique');
            $table->foreign(['administration_id', 'bank_statement_entry_id'], 'ber_entry_fk')->references(['administration_id', 'id'])->on('bank_statement_entries')->restrictOnDelete();
            $table->foreign(['administration_id', 'bank_transaction_id'], 'ber_transaction_fk')->references(['administration_id', 'id'])->on('bank_transactions')->restrictOnDelete();
            $table->foreign(['administration_id', 'bank_statement_entry_id', 'replaces_reconciliation_id'], 'ber_replaces_fk')->references(['administration_id', 'bank_statement_entry_id', 'id'])->on('bank_entry_reconciliations')->restrictOnDelete();
            $table->foreign('actor_id')->references('id')->on('domain_users')->restrictOnDelete();
        });
        Schema::create('bank_entry_active_reconciliations', function (Blueprint $table): void {
            $table->uuid('administration_id');
            $table->uuid('bank_statement_entry_id');
            $table->uuid('bank_entry_reconciliation_id');
            $table->primary(['administration_id', 'bank_statement_entry_id'], 'bear_primary');
            $table->unique(['administration_id', 'bank_entry_reconciliation_id'], 'bear_reconciliation_unique');
            $table->foreign(['administration_id', 'bank_statement_entry_id'], 'bear_entry_fk')->references(['administration_id', 'id'])->on('bank_statement_entries')->restrictOnDelete();
            $table->foreign(['administration_id', 'bank_statement_entry_id', 'bank_entry_reconciliation_id'], 'bear_reconciliation_fk')->references(['administration_id', 'bank_statement_entry_id', 'id'])->on('bank_entry_reconciliations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_entry_active_reconciliations');
        Schema::dropIfExists('bank_entry_reconciliations');
    }
};
