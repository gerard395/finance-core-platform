<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('open_item_settlements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->uuid('open_item_id');
            $table->date('effective_date');
            $table->text('amount');
            $table->char('currency', 3);
            $table->uuid('source_journal_entry_id');
            $table->string('type');
            $table->uuid('reversed_settlement_id')->nullable();
            $table->timestamps();

            $table->foreign(['administration_id', 'open_item_id'], 'ois_item_tenant_fk')
                ->references(['administration_id', 'id'])->on('open_items')->restrictOnDelete();
            $table->foreign(['administration_id', 'source_journal_entry_id'], 'ois_entry_tenant_fk')
                ->references(['administration_id', 'id'])->on('journal_entries')->restrictOnDelete();
            $table->unique(['administration_id', 'id']);
            $table->foreign(['administration_id', 'reversed_settlement_id'], 'ois_reversal_tenant_fk')
                ->references(['administration_id', 'id'])->on('open_item_settlements')->restrictOnDelete();
            $table->unique(['administration_id', 'reversed_settlement_id'], 'ois_one_reversal_unique');
            $table->index(['open_item_id', 'effective_date', 'id'], 'ois_history_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('open_item_settlements');
    }
};
