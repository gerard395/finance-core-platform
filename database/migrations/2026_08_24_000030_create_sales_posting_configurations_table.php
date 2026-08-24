<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_posting_configurations', function (Blueprint $table): void {
            $table->uuid('administration_id')->primary();
            $table->uuid('sales_journal_id');
            $table->uuid('accounts_receivable_ledger_account_id');
            $table->uuid('revenue_ledger_account_id');
            $table->uuid('output_vat_ledger_account_id');
            $table->timestamps();

            $table->foreign('administration_id', 'spc_admin_fk')->references('id')->on('administrations')->restrictOnDelete();
            $table->foreign(['administration_id', 'sales_journal_id'], 'spc_journal_tenant_fk')->references(['administration_id', 'id'])->on('journals')->restrictOnDelete();
            $table->foreign(['administration_id', 'accounts_receivable_ledger_account_id'], 'spc_ar_account_tenant_fk')->references(['administration_id', 'id'])->on('ledger_accounts')->restrictOnDelete();
            $table->foreign(['administration_id', 'revenue_ledger_account_id'], 'spc_revenue_account_tenant_fk')->references(['administration_id', 'id'])->on('ledger_accounts')->restrictOnDelete();
            $table->foreign(['administration_id', 'output_vat_ledger_account_id'], 'spc_vat_account_tenant_fk')->references(['administration_id', 'id'])->on('ledger_accounts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_posting_configurations');
    }
};
