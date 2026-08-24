<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->foreign(['administration_id', 'journal_id'], 'journal_entries_journal_tenant_fk')->references(['administration_id', 'id'])->on('journals')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->dropForeign('journal_entries_journal_tenant_fk');
        });
    }
};
