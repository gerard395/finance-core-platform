<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_invoice_lines', function (Blueprint $table): void {
            $table->json('international_tax_input')->nullable()->after('gross_amount');
            $table->json('tax_treatment_definition_snapshot')->nullable()->after('international_tax_input');
        });
        DB::statement('ALTER TABLE purchase_invoice_lines DROP CHECK pil_direction_check');
        DB::statement("ALTER TABLE purchase_invoice_lines ADD CONSTRAINT pil_direction_check CHECK (tax_direction_snapshot = 'input' OR (tax_direction_snapshot = 'output' AND international_tax_input IS NOT NULL))");
        DB::statement('ALTER TABLE purchase_invoice_lines ADD CONSTRAINT pil_treatment_snapshot_input_check CHECK (tax_treatment_definition_snapshot IS NULL OR international_tax_input IS NOT NULL)');
        Schema::table('purchase_posting_configurations', function (Blueprint $table): void {
            $table->uuid('vat_payable_ledger_account_id')->nullable()->after('input_vat_ledger_account_id');
            $table->foreign(['administration_id', 'vat_payable_ledger_account_id'], 'ppc_vat_payable_tenant_fk')
                ->references(['administration_id', 'id'])->on('ledger_accounts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_posting_configurations', function (Blueprint $table): void {
            $table->dropForeign('ppc_vat_payable_tenant_fk');
            $table->dropColumn('vat_payable_ledger_account_id');
        });
        DB::statement('ALTER TABLE purchase_invoice_lines DROP CHECK pil_direction_check');
        DB::statement('ALTER TABLE purchase_invoice_lines DROP CHECK pil_treatment_snapshot_input_check');
        Schema::table('purchase_invoice_lines', fn (Blueprint $table) => $table->dropColumn(['international_tax_input', 'tax_treatment_definition_snapshot']));
        DB::statement("ALTER TABLE purchase_invoice_lines ADD CONSTRAINT pil_direction_check CHECK (tax_direction_snapshot = 'input')");
    }
};
