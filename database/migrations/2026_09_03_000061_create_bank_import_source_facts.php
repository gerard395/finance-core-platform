<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_import_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->uuid('administration_bank_account_id');
            $table->string('source_format', 32);
            $table->string('namespace_version', 100);
            $table->char('original_file_hash', 64);
            $table->string('parser_version', 64);
            $table->string('canonicalization_version', 64);
            $table->uuid('actor_id');
            $table->dateTime('imported_at');
            $table->string('artifact_reference', 255);
            $table->foreign('administration_id')->references('id')->on('administrations')->restrictOnDelete();
            $table->foreign(['administration_id', 'administration_bank_account_id'], 'bib_bank_account_tenant_fk')->references(['administration_id', 'id'])->on('administration_bank_accounts')->restrictOnDelete();
            $table->foreign('actor_id')->references('id')->on('domain_users')->restrictOnDelete();
            $table->unique(['administration_id', 'id']);
            $table->index(['administration_id', 'administration_bank_account_id', 'original_file_hash'], 'bib_source_hash_idx');
        });
        Schema::create('bank_statements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->uuid('bank_import_batch_id');
            $table->string('external_id')->nullable();
            $table->string('electronic_sequence')->nullable();
            $table->string('account_identity', 64);
            $table->char('currency', 3);
            $table->decimal('opening_balance', 20, 8)->nullable();
            $table->decimal('closing_balance', 20, 8)->nullable();
            $table->dateTime('period_from')->nullable();
            $table->dateTime('period_to')->nullable();
            $table->char('canonical_statement_hash', 64);
            $table->unsignedInteger('source_ordinal');
            $table->foreign('administration_id')->references('id')->on('administrations')->restrictOnDelete();
            $table->foreign(['administration_id', 'bank_import_batch_id'], 'bs_batch_tenant_fk')->references(['administration_id', 'id'])->on('bank_import_batches')->restrictOnDelete();
            $table->unique(['administration_id', 'id']);
            $table->index(['administration_id', 'external_id']);
            $table->index(['administration_id', 'canonical_statement_hash']);
        });
        Schema::create('bank_statement_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->uuid('bank_statement_id');
            $table->date('booking_date');
            $table->date('value_date')->nullable();
            $table->decimal('signed_amount', 20, 8);
            $table->char('currency', 3);
            $table->string('direction', 4);
            $table->boolean('reversal');
            $table->string('account_servicer_reference')->nullable();
            $table->string('entry_reference')->nullable();
            $table->string('end_to_end_id')->nullable();
            $table->string('counterparty_name')->nullable();
            $table->string('counterparty_account', 64)->nullable();
            $table->json('remittance_lines');
            $table->string('creditor_reference')->nullable();
            $table->string('mandate_id')->nullable();
            $table->string('bank_transaction_domain')->nullable();
            $table->string('bank_transaction_family')->nullable();
            $table->string('bank_transaction_subfamily')->nullable();
            $table->string('bank_transaction_proprietary_code')->nullable();
            $table->json('normalized_metadata');
            $table->char('canonical_entry_hash', 64);
            $table->unsignedInteger('source_ordinal');
            $table->foreign('administration_id')->references('id')->on('administrations')->restrictOnDelete();
            $table->foreign(['administration_id', 'bank_statement_id'], 'bse_statement_tenant_fk')->references(['administration_id', 'id'])->on('bank_statements')->restrictOnDelete();
            $table->unique(['administration_id', 'id']);
            $table->index(['administration_id', 'canonical_entry_hash'], 'bse_canonical_hash_idx');
            $table->index(['administration_id', 'account_servicer_reference'], 'bse_servicer_ref_idx');
            $table->index(['administration_id', 'entry_reference'], 'bse_entry_ref_idx');
            $table->index(['administration_id', 'end_to_end_id'], 'bse_end_to_end_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_entries');
        Schema::dropIfExists('bank_statements');
        Schema::dropIfExists('bank_import_batches');
    }
};
