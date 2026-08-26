<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->uuid('administration_id');
            $t->uuid('administration_bank_account_id');
            $t->date('transaction_date');
            $t->decimal('amount', 20, 8);
            $t->char('currency', 3);
            $t->string('reference', 255);
            $t->string('description', 1000);
            $t->string('status', 20);
            $t->uuid('created_by');
            $t->timestamp('created_at');
            $t->uuid('finalized_by')->nullable();
            $t->timestamp('finalized_at')->nullable();
            $t->unique(['administration_id', 'id']);
            $t->foreign(['administration_id', 'administration_bank_account_id'], 'bt_bank_tenant_fk')->references(['administration_id', 'id'])->on('administration_bank_accounts')->restrictOnDelete();
            $t->foreign('created_by')->references('id')->on('domain_users')->restrictOnDelete();
            $t->foreign('finalized_by')->references('id')->on('domain_users')->restrictOnDelete();
            $t->index(['administration_id', 'transaction_date']);
        });
        Schema::create('payments', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->uuid('administration_id');
            $t->uuid('bank_transaction_id');
            $t->uuid('relation_id');
            $t->string('type', 30);
            $t->decimal('amount', 20, 8);
            $t->char('currency', 3);
            $t->unique(['administration_id', 'id']);
            $t->unique(['administration_id', 'bank_transaction_id']);
            $t->foreign(['administration_id', 'bank_transaction_id'], 'pay_tx_tenant_fk')->references(['administration_id', 'id'])->on('bank_transactions')->restrictOnDelete();
            $t->foreign(['administration_id', 'relation_id'], 'pay_relation_tenant_fk')->references(['administration_id', 'id'])->on('relations')->restrictOnDelete();
        });
        Schema::create('payment_allocations', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->uuid('administration_id');
            $t->uuid('payment_id');
            $t->uuid('open_item_id');
            $t->decimal('amount', 20, 8);
            $t->char('currency', 3);
            $t->string('open_item_type', 20)->nullable();
            $t->string('open_item_side', 20)->nullable();
            $t->uuid('relation_id_snapshot')->nullable();
            $t->uuid('control_ledger_account_id_snapshot')->nullable();
            $t->unique(['administration_id', 'payment_id', 'open_item_id'], 'alloc_payment_item_unique');
            $t->foreign(['administration_id', 'payment_id'], 'alloc_payment_tenant_fk')->references(['administration_id', 'id'])->on('payments')->restrictOnDelete();
            $t->foreign(['administration_id', 'open_item_id'], 'alloc_item_tenant_fk')->references(['administration_id', 'id'])->on('open_items')->restrictOnDelete();
            $t->foreign(['administration_id', 'relation_id_snapshot'], 'alloc_relation_tenant_fk')->references(['administration_id', 'id'])->on('relations')->restrictOnDelete();
            $t->foreign(['administration_id', 'control_ledger_account_id_snapshot'], 'alloc_control_tenant_fk')->references(['administration_id', 'id'])->on('ledger_accounts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('bank_transactions');
    }
};
