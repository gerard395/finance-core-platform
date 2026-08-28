<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_allocations', function (Blueprint $table): void {
            $table->unique(['administration_id', 'id'], 'pa_tenant_id_unique');
            $table->unique(['administration_id', 'open_item_id', 'id'], 'pa_tenant_item_id_unique');
        });
        Schema::table('bank_transaction_postings', function (Blueprint $table): void {
            $table->unique(['administration_id', 'bank_transaction_id', 'id'], 'btp_tenant_tx_id_unique');
        });
        Schema::table('open_item_settlements', function (Blueprint $table): void {
            $table->unique(['administration_id', 'open_item_id', 'id'], 'ois_tenant_item_id_unique');
        });

        Schema::create('bank_transaction_reversals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->uuid('original_bank_transaction_id');
            $table->uuid('original_bank_transaction_posting_id');
            $table->uuid('original_journal_entry_id');
            $table->uuid('reversal_journal_entry_id');
            $table->date('reversal_posting_date');
            $table->string('reason', 500);
            $table->uuid('reversed_by');
            $table->timestamp('reversed_at');
            $table->timestamp('created_at');
            $table->unique(['administration_id', 'id'], 'btr_tenant_id_unique');
            $table->unique(['administration_id', 'original_bank_transaction_id'], 'btr_original_tx_unique');
            $table->unique(['administration_id', 'reversal_journal_entry_id'], 'btr_reversal_entry_unique');
            $table->foreign(['administration_id', 'original_bank_transaction_id'], 'btr_original_tx_fk')->references(['administration_id', 'id'])->on('bank_transactions')->restrictOnDelete();
            $table->foreign(['administration_id', 'original_bank_transaction_id', 'original_bank_transaction_posting_id'], 'btr_original_posting_fk')->references(['administration_id', 'bank_transaction_id', 'id'])->on('bank_transaction_postings')->restrictOnDelete();
            $table->foreign(['administration_id', 'original_journal_entry_id'], 'btr_original_entry_fk')->references(['administration_id', 'id'])->on('journal_entries')->restrictOnDelete();
            $table->foreign(['administration_id', 'reversal_journal_entry_id'], 'btr_reversal_entry_fk')->references(['administration_id', 'id'])->on('journal_entries')->restrictOnDelete();
            $table->foreign('reversed_by', 'btr_actor_fk')->references('id')->on('domain_users')->restrictOnDelete();
        });

        Schema::create('bank_transaction_settlement_reversal_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->uuid('bank_transaction_reversal_id');
            $table->uuid('payment_allocation_id');
            $table->uuid('open_item_id');
            $table->uuid('original_open_item_settlement_id');
            $table->uuid('reversal_open_item_settlement_id');
            $table->timestamp('created_at');
            $table->unique(['administration_id', 'id'], 'btsrl_tenant_id_unique');
            $table->unique(['administration_id', 'original_open_item_settlement_id'], 'btsrl_original_unique');
            $table->unique(['administration_id', 'reversal_open_item_settlement_id'], 'btsrl_reversal_unique');
            $table->unique(['administration_id', 'payment_allocation_id'], 'btsrl_allocation_unique');
            $table->foreign(['administration_id', 'bank_transaction_reversal_id'], 'btsrl_reversal_fact_fk')->references(['administration_id', 'id'])->on('bank_transaction_reversals')->restrictOnDelete();
            $table->foreign(['administration_id', 'open_item_id', 'payment_allocation_id'], 'btsrl_allocation_fk')->references(['administration_id', 'open_item_id', 'id'])->on('payment_allocations')->restrictOnDelete();
            $table->foreign(['administration_id', 'open_item_id', 'original_open_item_settlement_id'], 'btsrl_original_settlement_fk')->references(['administration_id', 'open_item_id', 'id'])->on('open_item_settlements')->restrictOnDelete();
            $table->foreign(['administration_id', 'open_item_id', 'reversal_open_item_settlement_id'], 'btsrl_reversal_settlement_fk')->references(['administration_id', 'open_item_id', 'id'])->on('open_item_settlements')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transaction_settlement_reversal_links');
        Schema::dropIfExists('bank_transaction_reversals');
        Schema::table('open_item_settlements', fn (Blueprint $table) => $table->dropUnique('ois_tenant_item_id_unique'));
        Schema::table('bank_transaction_postings', fn (Blueprint $table) => $table->dropUnique('btp_tenant_tx_id_unique'));
        Schema::table('payment_allocations', function (Blueprint $table): void {
            $table->dropUnique('pa_tenant_item_id_unique');
            $table->dropUnique('pa_tenant_id_unique');
        });
    }
};
