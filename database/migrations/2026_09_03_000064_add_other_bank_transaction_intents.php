<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('other_bank_transaction_intents', function (Blueprint $table): void {
            $table->uuid('bank_transaction_id')->primary();
            $table->uuid('administration_id');
            $table->uuid('contra_ledger_account_id');
            $table->decimal('amount', 20, 8);
            $table->char('currency', 3);
            $table->foreign(['administration_id', 'bank_transaction_id'], 'obti_transaction_fk')->references(['administration_id', 'id'])->on('bank_transactions')->restrictOnDelete();
            $table->foreign(['administration_id', 'contra_ledger_account_id'], 'obti_contra_fk')->references(['administration_id', 'id'])->on('ledger_accounts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('other_bank_transaction_intents');
    }
};
