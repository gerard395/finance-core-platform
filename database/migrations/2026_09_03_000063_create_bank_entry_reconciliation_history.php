<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_entry_reconciliation_history', function (Blueprint $table): void {
            $table->bigIncrements('sequence');
            $table->uuid('id')->unique();
            $table->uuid('administration_id');
            $table->uuid('bank_statement_entry_id');
            $table->string('action', 32);
            $table->uuid('predecessor_id')->nullable()->unique();
            $table->string('reason', 500);
            $table->uuid('actor_id');
            $table->dateTime('occurred_at', 6);
            $table->foreign(['administration_id', 'bank_statement_entry_id'], 'berh_entry_tenant_fk')->references(['administration_id', 'id'])->on('bank_statement_entries')->restrictOnDelete();
            $table->foreign('actor_id', 'berh_actor_fk')->references('id')->on('domain_users')->restrictOnDelete();
            $table->unique(['administration_id', 'id']);
            $table->unique(['administration_id', 'bank_statement_entry_id', 'id'], 'berh_entry_id_unique');
            $table->index(['administration_id', 'bank_statement_entry_id', 'sequence'], 'berh_entry_sequence_idx');
        });
        Schema::table('bank_entry_reconciliation_history', function (Blueprint $table): void {
            $table->foreign(['administration_id', 'bank_statement_entry_id', 'predecessor_id'], 'berh_predecessor_fk')->references(['administration_id', 'bank_statement_entry_id', 'id'])->on('bank_entry_reconciliation_history')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE bank_entry_reconciliation_history ADD CONSTRAINT berh_action_check CHECK (action IN ('ignored', 'restored_from_ignored'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_entry_reconciliation_history');
    }
};
