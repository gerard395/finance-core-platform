<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('relations', function (Blueprint $table): void {
            $table->string('vat_identification_number', 32)->nullable()->after('display_name');
            $table->char('fiscal_jurisdiction', 2)->nullable()->after('vat_identification_number');
        });

        Schema::table('administrations', function (Blueprint $table): void {
            $table->char('fiscal_jurisdiction', 2)->nullable()->after('organisation_vat_number');
        });
    }

    public function down(): void
    {
        Schema::table('relations', function (Blueprint $table): void {
            $table->dropColumn(['vat_identification_number', 'fiscal_jurisdiction']);
        });

        Schema::table('administrations', function (Blueprint $table): void {
            $table->dropColumn('fiscal_jurisdiction');
        });
    }
};
