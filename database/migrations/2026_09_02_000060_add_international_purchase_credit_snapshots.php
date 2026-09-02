<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_credit_invoice_lines', function (Blueprint $table): void {
            $table->json('international_tax_snapshot')->nullable()->after('source_tax_posting_id');
        });
        DB::statement('ALTER TABLE purchase_credit_invoice_lines DROP CHECK pcil_direction_check');
        DB::statement("ALTER TABLE purchase_credit_invoice_lines ADD CONSTRAINT pcil_direction_check CHECK ((international_tax_snapshot IS NULL AND tax_direction_snapshot='input') OR (international_tax_snapshot IS NOT NULL AND tax_direction_snapshot IN ('input','output')))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE purchase_credit_invoice_lines DROP CHECK pcil_direction_check');
        DB::statement("ALTER TABLE purchase_credit_invoice_lines ADD CONSTRAINT pcil_direction_check CHECK (tax_direction_snapshot='input')");
        Schema::table('purchase_credit_invoice_lines', function (Blueprint $table): void {
            $table->dropColumn('international_tax_snapshot');
        });
    }
};
