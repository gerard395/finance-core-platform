<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_postings', function (Blueprint $table): void {
            $table->index(['administration_id', 'source_document_type', 'source_document_id', 'type', 'source_line_id'], 'tp_source_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tax_postings', function (Blueprint $table): void {
            $table->dropIndex('tp_source_lookup_idx');
        });
    }
};
