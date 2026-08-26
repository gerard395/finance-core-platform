<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banking_posting_configurations', function (Blueprint $table): void {
            $table->uuid('administration_id');
            $table->uuid('administration_bank_account_id');
            $table->uuid('bank_journal_id');
            $table->uuid('bank_ledger_account_id');
            $table->timestamps();
            $table->primary(['administration_id', 'administration_bank_account_id'], 'banking_config_pk');
            $table->foreign(['administration_id', 'administration_bank_account_id'], 'banking_config_account_tenant_fk')->references(['administration_id', 'id'])->on('administration_bank_accounts')->restrictOnDelete();
            $table->foreign(['administration_id', 'bank_journal_id'], 'banking_config_journal_tenant_fk')->references(['administration_id', 'id'])->on('journals')->restrictOnDelete();
            $table->foreign(['administration_id', 'bank_ledger_account_id'], 'banking_config_ledger_tenant_fk')->references(['administration_id', 'id'])->on('ledger_accounts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banking_posting_configurations');
    }
};
