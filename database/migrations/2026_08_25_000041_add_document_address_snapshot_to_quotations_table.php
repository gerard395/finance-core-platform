<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->uuid('quotation_address_id_snapshot')->nullable()->after('customer_name_snapshot');
            $table->string('quotation_address_type_snapshot', 16)->nullable()->after('quotation_address_id_snapshot');
            $table->string('quotation_address_line_1_snapshot')->nullable()->after('quotation_address_type_snapshot');
            $table->string('quotation_address_line_2_snapshot')->nullable()->after('quotation_address_line_1_snapshot');
            $table->string('quotation_postal_code_snapshot', 16)->nullable()->after('quotation_address_line_2_snapshot');
            $table->string('quotation_city_snapshot')->nullable()->after('quotation_postal_code_snapshot');
            $table->char('quotation_country_code_snapshot', 2)->nullable()->after('quotation_city_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->dropColumn([
                'quotation_address_id_snapshot',
                'quotation_address_type_snapshot',
                'quotation_address_line_1_snapshot',
                'quotation_address_line_2_snapshot',
                'quotation_postal_code_snapshot',
                'quotation_city_snapshot',
                'quotation_country_code_snapshot',
            ]);
        });
    }
};
