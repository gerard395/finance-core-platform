<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('open_items', function (Blueprint $table): void {
            $table->string('side')->nullable()->after('open_item_type');
        });

        DB::table('open_items')->where('open_item_type', 'receivable')->update(['side' => 'debit']);
        DB::table('open_items')->where('open_item_type', 'payable')->update(['side' => 'credit']);

        Schema::table('open_items', function (Blueprint $table): void {
            $table->string('side')->nullable(false)->change();
        });
        DB::statement("ALTER TABLE open_items ADD CONSTRAINT open_items_side_check CHECK (side IN ('debit', 'credit'))");

        Schema::create('open_item_matches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->uuid('debit_open_item_id');
            $table->uuid('credit_open_item_id');
            $table->text('amount');
            $table->char('currency', 3);
            $table->date('occurred_on');
            $table->uuid('source_journal_entry_id');
            $table->timestamps();

            $table->unique(['administration_id', 'id'], 'oim_tenant_id_unique');
            $table->foreign(['administration_id', 'debit_open_item_id'], 'oim_debit_tenant_fk')
                ->references(['administration_id', 'id'])->on('open_items')->restrictOnDelete();
            $table->foreign(['administration_id', 'credit_open_item_id'], 'oim_credit_tenant_fk')
                ->references(['administration_id', 'id'])->on('open_items')->restrictOnDelete();
            $table->foreign(['administration_id', 'source_journal_entry_id'], 'oim_entry_tenant_fk')
                ->references(['administration_id', 'id'])->on('journal_entries')->restrictOnDelete();
            $table->index(['debit_open_item_id', 'occurred_on', 'id'], 'oim_debit_history_idx');
            $table->index(['credit_open_item_id', 'occurred_on', 'id'], 'oim_credit_history_idx');
        });
        DB::statement('ALTER TABLE open_item_matches ADD CONSTRAINT oim_distinct_items_check CHECK (debit_open_item_id <> credit_open_item_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('open_item_matches');

        DB::statement('ALTER TABLE open_items DROP CHECK open_items_side_check');
        Schema::table('open_items', function (Blueprint $table): void {
            $table->dropColumn('side');
        });
    }
};
