<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_posting_configurations', function (Blueprint $table): void {
            $table->uuid('administration_id')->primary();
            $table->uuid('purchase_journal_id');
            $table->uuid('accounts_payable_ledger_account_id');
            $table->uuid('input_vat_ledger_account_id');
            $table->timestamps();
            $table->foreign('administration_id', 'ppc_admin_fk')->references('id')->on('administrations')->restrictOnDelete();
            $table->foreign(['administration_id', 'purchase_journal_id'], 'ppc_journal_tenant_fk')->references(['administration_id', 'id'])->on('journals')->restrictOnDelete();
            $table->foreign(['administration_id', 'accounts_payable_ledger_account_id'], 'ppc_ap_account_tenant_fk')->references(['administration_id', 'id'])->on('ledger_accounts')->restrictOnDelete();
            $table->foreign(['administration_id', 'input_vat_ledger_account_id'], 'ppc_vat_account_tenant_fk')->references(['administration_id', 'id'])->on('ledger_accounts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_posting_configurations');
    }
};
