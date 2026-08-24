<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $canonical = [
            'BTW21' => ['21', 'domestic_standard', 'domestic_standard', 'none'],
            'BTW9' => ['9', 'domestic_reduced', 'domestic_reduced', 'none'],
            'BTW0' => ['0', 'zero_rated', 'domestic_zero_rated', 'none'],
        ];

        foreach (DB::table('tax_codes')->get(['id', 'code', 'rate', 'direction']) as $row) {
            $mapping = $canonical[$row->code] ?? null;
            if ($mapping === null || (string) $row->rate !== $mapping[0] || $row->direction !== 'output') {
                throw new RuntimeException('Existing TaxCode cannot be factually classified.');
            }
        }

        foreach (DB::table('sales_invoice_lines')->get(['tax_code_snapshot', 'tax_rate_snapshot', 'tax_direction_snapshot']) as $row) {
            $mapping = $canonical[$row->tax_code_snapshot] ?? null;
            if ($mapping === null || (string) $row->tax_rate_snapshot !== $mapping[0] || $row->tax_direction_snapshot !== 'output') {
                throw new RuntimeException('Existing Sales tax snapshot cannot be factually classified.');
            }
        }

        Schema::table('tax_codes', function (Blueprint $table): void {
            $table->string('treatment', 40)->nullable()->after('rate');
            $table->string('vat_return_classification', 40)->nullable()->after('treatment');
            $table->string('icp_classification', 20)->nullable()->after('vat_return_classification');
        });
        Schema::table('sales_invoice_lines', function (Blueprint $table): void {
            $table->string('tax_treatment_snapshot', 40)->nullable()->after('tax_direction_snapshot');
            $table->string('vat_return_classification_snapshot', 40)->nullable()->after('tax_treatment_snapshot');
            $table->string('icp_classification_snapshot', 20)->nullable()->after('vat_return_classification_snapshot');
        });
        Schema::table('tax_postings', function (Blueprint $table): void {
            $table->string('treatment', 40)->nullable()->after('tax_rate');
            $table->string('vat_return_classification', 40)->nullable()->after('treatment');
            $table->string('icp_classification', 20)->nullable()->after('vat_return_classification');
        });

        foreach ($canonical as $code => [$rate, $treatment, $vat, $icp]) {
            DB::table('tax_codes')->where('code', $code)->where('rate', $rate)->where('direction', 'output')->update([
                'treatment' => $treatment, 'vat_return_classification' => $vat, 'icp_classification' => $icp,
            ]);
            DB::table('sales_invoice_lines')->where('tax_code_snapshot', $code)->where('tax_rate_snapshot', $rate)->where('tax_direction_snapshot', 'output')->update([
                'tax_treatment_snapshot' => $treatment, 'vat_return_classification_snapshot' => $vat, 'icp_classification_snapshot' => $icp,
            ]);
        }

        DB::statement('UPDATE tax_postings tp JOIN tax_codes tc ON tc.administration_id = tp.administration_id AND tc.id = tp.tax_code_id SET tp.treatment = tc.treatment, tp.vat_return_classification = tc.vat_return_classification, tp.icp_classification = tc.icp_classification WHERE tp.tax_rate = tc.rate AND tp.direction = tc.direction');

        foreach (['tax_codes', 'sales_invoice_lines', 'tax_postings'] as $table) {
            $columns = $table === 'sales_invoice_lines'
                ? ['tax_treatment_snapshot', 'vat_return_classification_snapshot', 'icp_classification_snapshot']
                : ['treatment', 'vat_return_classification', 'icp_classification'];
            if (DB::table($table)->whereNull($columns[0])->orWhereNull($columns[1])->orWhereNull($columns[2])->exists()) {
                throw new RuntimeException("Existing {$table} rows could not be factually classified.");
            }
        }

        Schema::table('tax_codes', function (Blueprint $table): void {
            $table->string('treatment', 40)->nullable(false)->change();
            $table->string('vat_return_classification', 40)->nullable(false)->change();
            $table->string('icp_classification', 20)->nullable(false)->change();
        });
        Schema::table('sales_invoice_lines', function (Blueprint $table): void {
            $table->string('tax_treatment_snapshot', 40)->nullable(false)->change();
            $table->string('vat_return_classification_snapshot', 40)->nullable(false)->change();
            $table->string('icp_classification_snapshot', 20)->nullable(false)->change();
        });
        Schema::table('tax_postings', function (Blueprint $table): void {
            $table->string('treatment', 40)->nullable(false)->change();
            $table->string('vat_return_classification', 40)->nullable(false)->change();
            $table->string('icp_classification', 20)->nullable(false)->change();
        });

        $this->addChecks('tax_codes', 'tc', 'treatment', 'vat_return_classification', 'icp_classification');
        $this->addChecks('sales_invoice_lines', 'sil', 'tax_treatment_snapshot', 'vat_return_classification_snapshot', 'icp_classification_snapshot');
        $this->addChecks('tax_postings', 'tp', 'treatment', 'vat_return_classification', 'icp_classification');
    }

    public function down(): void
    {
        foreach ([['tax_codes', 'tc'], ['sales_invoice_lines', 'sil'], ['tax_postings', 'tp']] as [$table, $prefix]) {
            foreach (['treatment', 'vat_return', 'icp'] as $suffix) {
                DB::statement("ALTER TABLE {$table} DROP CHECK {$prefix}_{$suffix}_check");
            }
        }
        Schema::table('tax_codes', fn (Blueprint $table) => $table->dropColumn(['treatment', 'vat_return_classification', 'icp_classification']));
        Schema::table('sales_invoice_lines', fn (Blueprint $table) => $table->dropColumn(['tax_treatment_snapshot', 'vat_return_classification_snapshot', 'icp_classification_snapshot']));
        Schema::table('tax_postings', fn (Blueprint $table) => $table->dropColumn(['treatment', 'vat_return_classification', 'icp_classification']));
    }

    private function addChecks(string $table, string $prefix, string $treatment, string $vat, string $icp): void
    {
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$prefix}_treatment_check CHECK ({$treatment} IN ('domestic_standard','domestic_reduced','zero_rated','reverse_charge_eu_service','intra_community_goods','exempt','outside_scope'))");
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$prefix}_vat_return_check CHECK ({$vat} IN ('domestic_standard','domestic_reduced','domestic_zero_rated','eu_services','intra_community_supplies','exempt','outside_scope'))");
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$prefix}_icp_check CHECK ({$icp} IN ('none','service','goods_supply'))");
    }
};
