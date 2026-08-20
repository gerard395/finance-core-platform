<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $table): void {
            $table->unique(['administration_id', 'journal_entry_id', 'id'], 'jel_tenant_entry_id_unique');
        });

        Schema::create('tax_postings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->uuid('tax_code_id');
            $table->string('tax_rate', 16);
            $table->text('taxable_base');
            $table->text('tax_amount');
            $table->char('currency', 3);
            $table->string('direction');
            $table->string('type');
            $table->string('source_document_type');
            $table->uuid('source_document_id');
            $table->uuid('source_line_id');
            $table->date('posting_date');
            $table->uuid('journal_entry_id');
            $table->uuid('base_journal_entry_line_id');
            $table->uuid('tax_journal_entry_line_id')->nullable();
            $table->uuid('reversed_tax_posting_id')->nullable();
            $table->timestamps();

            $table->foreign('administration_id')->references('id')->on('administrations')->restrictOnDelete();
            $table->foreign(['administration_id', 'journal_entry_id'], 'tp_entry_tenant_fk')
                ->references(['administration_id', 'id'])->on('journal_entries')->restrictOnDelete();
            $table->foreign(
                ['administration_id', 'journal_entry_id', 'base_journal_entry_line_id'],
                'tp_base_line_tenant_fk',
            )->references(['administration_id', 'journal_entry_id', 'id'])->on('journal_entry_lines')->restrictOnDelete();
            $table->foreign(
                ['administration_id', 'journal_entry_id', 'tax_journal_entry_line_id'],
                'tp_tax_line_tenant_fk',
            )->references(['administration_id', 'journal_entry_id', 'id'])->on('journal_entry_lines')->restrictOnDelete();
            $table->unique(['administration_id', 'id']);
            $table->foreign(['administration_id', 'reversed_tax_posting_id'], 'tp_reversal_tenant_fk')
                ->references(['administration_id', 'id'])->on('tax_postings')->restrictOnDelete();
            $table->unique(['administration_id', 'reversed_tax_posting_id'], 'tp_one_reversal_unique');
            $table->index(['administration_id', 'posting_date', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_postings');

        Schema::table('journal_entry_lines', function (Blueprint $table): void {
            $table->dropUnique('jel_tenant_entry_id_unique');
        });
    }
};
