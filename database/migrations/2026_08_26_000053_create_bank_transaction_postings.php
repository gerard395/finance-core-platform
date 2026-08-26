<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_transactions', function (Blueprint $t): void {
            $t->uuid('posted_by')->nullable()->after('finalized_at');
            $t->timestamp('posted_at')->nullable()->after('posted_by');
            $t->foreign('posted_by')->references('id')->on('domain_users')->restrictOnDelete();
        });
        Schema::create('bank_transaction_postings', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->uuid('administration_id');
            $t->uuid('bank_transaction_id');
            $t->uuid('journal_entry_id');
            $t->date('posting_date');
            $t->timestamps();
            $t->unique(['administration_id', 'id']);
            $t->unique(['administration_id', 'bank_transaction_id'], 'bank_posting_tx_unique');
            $t->unique(['administration_id', 'journal_entry_id'], 'bank_posting_entry_unique');
            $t->foreign(['administration_id', 'bank_transaction_id'], 'bank_posting_tx_fk')->references(['administration_id', 'id'])->on('bank_transactions')->restrictOnDelete();
            $t->foreign(['administration_id', 'journal_entry_id'], 'bank_posting_entry_fk')->references(['administration_id', 'id'])->on('journal_entries')->restrictOnDelete();
        });
        Schema::table('open_item_settlements', function (Blueprint $t): void {
            $t->uuid('payment_allocation_id')->nullable()->after('open_item_id');
            $t->unique('payment_allocation_id', 'settlement_allocation_unique');
            $t->foreign('payment_allocation_id')->references('id')->on('payment_allocations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('open_item_settlements', function (Blueprint $t): void {
            $t->dropForeign(['payment_allocation_id']);
            $t->dropUnique('settlement_allocation_unique');
            $t->dropColumn('payment_allocation_id');
        });
        Schema::dropIfExists('bank_transaction_postings');
        Schema::table('bank_transactions', function (Blueprint $t): void {
            $t->dropForeign(['posted_by']);
            $t->dropColumn(['posted_by', 'posted_at']);
        });
    }
};
