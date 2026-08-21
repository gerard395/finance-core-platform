<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relation_bank_accounts', function (Blueprint $table): void {
            $table->uuid('bank_account_id')->primary();
            $table->uuid('administration_id');
            $table->uuid('relation_id');
            $table->string('iban', 34);
            $table->string('bic', 11)->nullable();
            $table->string('account_name', 255);
            $table->boolean('active');
            $table->timestamps();

            $table->foreign(['administration_id', 'relation_id'], 'bank_account_relation_tenant_fk')
                ->references(['administration_id', 'id'])->on('relations')->restrictOnDelete();
            $table->index(['administration_id', 'relation_id', 'account_name', 'bank_account_id'], 'bank_account_relation_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relation_bank_accounts');
    }
};
