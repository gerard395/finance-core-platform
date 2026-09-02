<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_treatment_definitions', function (Blueprint $table): void {
            $table->uuid('id');
            $table->uuid('administration_id');
            $table->uuid('tax_code_id');
            $table->unsignedInteger('version');
            $table->string('treatment_type', 48);
            $table->char('jurisdiction', 2);
            $table->string('vat_rate', 8);
            $table->string('supplier_vat_mode', 24);
            $table->string('deductibility_policy', 32);
            $table->json('leg_definitions');
            $table->boolean('active')->default(true);
            $table->dateTime('effective_from')->nullable();
            $table->timestamps();

            $table->primary(['id', 'version']);
            $table->foreign('administration_id')->references('id')->on('administrations')->restrictOnDelete();
            $table->foreign(['administration_id', 'tax_code_id'], 'ttd_tax_code_tenant_fk')
                ->references(['administration_id', 'id'])->on('tax_codes')->restrictOnDelete();
            $table->unique(['administration_id', 'id', 'version'], 'ttd_tenant_id_version_unique');
            $table->unique(['administration_id', 'tax_code_id', 'version'], 'ttd_tax_code_version_unique');
            $table->index(['administration_id', 'tax_code_id', 'active', 'version'], 'ttd_active_lookup');
        });

        Schema::table('tax_postings', function (Blueprint $table): void {
            $table->uuid('tax_treatment_definition_id')->nullable()->after('tax_code_id');
            $table->unsignedInteger('tax_treatment_definition_version')->nullable()->after('tax_treatment_definition_id');
            $table->uuid('tax_treatment_group_id')->nullable()->after('tax_treatment_definition_version');
            $table->string('tax_leg_role', 24)->nullable()->after('tax_treatment_group_id');
            $table->string('treatment_type', 48)->nullable()->after('tax_leg_role');
            $table->char('tax_jurisdiction', 2)->nullable()->after('treatment_type');
            $table->string('reporting_classification', 48)->nullable()->after('tax_jurisdiction');
            $table->unsignedSmallInteger('deductibility_basis_points')->nullable()->after('reporting_classification');
            $table->text('assessed_vat')->nullable()->after('deductibility_basis_points');
            $table->text('deductible_vat')->nullable()->after('assessed_vat');
            $table->text('non_deductible_tax_cost')->nullable()->after('deductible_vat');
            $table->string('supplier_vat_mode', 24)->nullable()->after('non_deductible_tax_cost');

            $table->foreign(
                ['administration_id', 'tax_treatment_definition_id', 'tax_treatment_definition_version'],
                'tp_treatment_definition_tenant_fk',
            )->references(['administration_id', 'id', 'version'])->on('tax_treatment_definitions')->restrictOnDelete();
            $table->unique(
                ['administration_id', 'source_document_type', 'source_document_id', 'source_line_id', 'tax_treatment_group_id', 'tax_leg_role', 'type'],
                'tp_treatment_group_role_unique',
            );
            $table->index(['administration_id', 'tax_treatment_group_id', 'id'], 'tp_treatment_group_lookup');
        });

        DB::statement('ALTER TABLE tax_postings ADD CONSTRAINT tp_new_leg_all_or_none CHECK ((tax_treatment_group_id IS NULL AND tax_leg_role IS NULL AND tax_treatment_definition_id IS NULL AND tax_treatment_definition_version IS NULL AND treatment_type IS NULL AND tax_jurisdiction IS NULL AND reporting_classification IS NULL AND deductibility_basis_points IS NULL AND assessed_vat IS NULL AND deductible_vat IS NULL AND non_deductible_tax_cost IS NULL AND supplier_vat_mode IS NULL) OR (tax_treatment_group_id IS NOT NULL AND tax_leg_role IS NOT NULL AND tax_treatment_definition_id IS NOT NULL AND tax_treatment_definition_version IS NOT NULL AND treatment_type IS NOT NULL AND tax_jurisdiction IS NOT NULL AND reporting_classification IS NOT NULL AND deductibility_basis_points BETWEEN 0 AND 10000 AND assessed_vat IS NOT NULL AND deductible_vat IS NOT NULL AND non_deductible_tax_cost IS NOT NULL AND supplier_vat_mode IS NOT NULL))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tax_postings DROP CHECK tp_new_leg_all_or_none');
        Schema::table('tax_postings', function (Blueprint $table): void {
            $table->dropForeign('tp_treatment_definition_tenant_fk');
            $table->dropUnique('tp_treatment_group_role_unique');
            $table->dropIndex('tp_treatment_group_lookup');
            $table->dropColumn([
                'tax_treatment_definition_id', 'tax_treatment_definition_version', 'tax_treatment_group_id',
                'tax_leg_role', 'treatment_type', 'tax_jurisdiction', 'reporting_classification',
                'deductibility_basis_points', 'assessed_vat', 'deductible_vat',
                'non_deductible_tax_cost', 'supplier_vat_mode',
            ]);
        });
        Schema::dropIfExists('tax_treatment_definitions');
    }
};
