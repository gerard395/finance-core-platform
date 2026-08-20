<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entry_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->uuid('journal_entry_id');
            $table->uuid('ledger_account_id');
            $table->text('debit_amount')->nullable();
            $table->text('credit_amount')->nullable();
            $table->char('currency', 3);
            $table->string('description');
            $table->timestamps();

            $table->foreign(['administration_id', 'journal_entry_id'], 'jel_entry_tenant_fk')
                ->references(['administration_id', 'id'])->on('journal_entries')->restrictOnDelete();
            $table->foreign(['administration_id', 'ledger_account_id'], 'jel_account_tenant_fk')
                ->references(['administration_id', 'id'])->on('ledger_accounts')->restrictOnDelete();
            $table->index(['journal_entry_id', 'id'], 'jel_entry_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
    }
};
