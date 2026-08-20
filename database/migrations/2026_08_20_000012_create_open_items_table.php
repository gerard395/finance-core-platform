<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('open_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->uuid('relation_id');
            $table->uuid('journal_entry_id');
            $table->text('original_amount');
            $table->char('currency', 3);
            $table->date('opened_on');
            $table->timestamps();

            $table->foreign('administration_id')->references('id')->on('administrations')->restrictOnDelete();
            $table->foreign(['administration_id', 'journal_entry_id'], 'oi_entry_tenant_fk')
                ->references(['administration_id', 'id'])->on('journal_entries')->restrictOnDelete();
            $table->unique(['administration_id', 'id']);
            $table->index(['administration_id', 'opened_on', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('open_items');
    }
};
