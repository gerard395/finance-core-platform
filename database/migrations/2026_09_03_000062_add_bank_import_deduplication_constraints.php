<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_import_batches', function (Blueprint $table): void {
            $table->unique(['administration_id', 'administration_bank_account_id', 'original_file_hash'], 'bib_source_unique');
        });
        Schema::table('bank_statements', function (Blueprint $table): void {
            $table->uuid('administration_bank_account_id')->after('administration_id');
            $table->string('source_format', 32)->after('administration_bank_account_id');
            $table->string('namespace_version', 100)->after('source_format');
            $table->string('source_identity_kind', 32)->after('canonical_statement_hash');
            $table->string('source_identity_value', 255)->after('source_identity_kind');
            $table->string('source_identity_version', 64)->after('source_identity_value');
            $table->foreign(['administration_id', 'administration_bank_account_id'], 'bs_bank_account_tenant_fk')->references(['administration_id', 'id'])->on('administration_bank_accounts')->restrictOnDelete();
            $table->unique(['administration_id', 'administration_bank_account_id', 'namespace_version', 'source_identity_kind', 'source_identity_value'], 'bs_source_identity_unique');
        });
        Schema::table('bank_statement_entries', function (Blueprint $table): void {
            $table->uuid('administration_bank_account_id')->after('administration_id');
            $table->string('deduplication_kind', 32)->after('canonical_entry_hash');
            $table->string('deduplication_value', 255)->after('deduplication_kind');
            $table->string('deduplication_version', 64)->after('deduplication_value');
            $table->foreign(['administration_id', 'administration_bank_account_id'], 'bse_bank_account_tenant_fk')->references(['administration_id', 'id'])->on('administration_bank_accounts')->restrictOnDelete();
            $table->unique(['administration_id', 'administration_bank_account_id', 'deduplication_kind', 'deduplication_value'], 'bse_dedupe_identity_unique');
        });
    }

    public function down(): void
    {
        Schema::table('bank_statement_entries', function (Blueprint $table): void {
            $table->dropUnique('bse_dedupe_identity_unique');
            $table->dropForeign('bse_bank_account_tenant_fk');
            $table->dropColumn(['administration_bank_account_id', 'deduplication_kind', 'deduplication_value', 'deduplication_version']);
        });
        Schema::table('bank_statements', function (Blueprint $table): void {
            $table->dropUnique('bs_source_identity_unique');
            $table->dropForeign('bs_bank_account_tenant_fk');
            $table->dropColumn(['administration_bank_account_id', 'source_format', 'namespace_version', 'source_identity_kind', 'source_identity_value', 'source_identity_version']);
        });
        Schema::table('bank_import_batches', function (Blueprint $table): void {
            $table->dropUnique('bib_source_unique');
        });
    }
};
