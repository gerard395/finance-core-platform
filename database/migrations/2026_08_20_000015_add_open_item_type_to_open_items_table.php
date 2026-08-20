<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('open_items', function (Blueprint $table): void {
            $table->enum('open_item_type', ['receivable', 'payable'])->after('journal_entry_id');
        });

        DB::statement("ALTER TABLE open_items ADD CONSTRAINT open_items_type_check CHECK (open_item_type IN ('receivable', 'payable'))");
    }

    public function down(): void
    {
        $constraint = DB::selectOne(<<<'SQL'
            SELECT CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
              AND TABLE_NAME = 'open_items'
              AND CONSTRAINT_NAME = 'open_items_type_check'
            SQL);

        if ($constraint !== null) {
            DB::statement('ALTER TABLE open_items DROP CHECK open_items_type_check');
        }

        Schema::table('open_items', function (Blueprint $table): void {
            $table->dropColumn('open_item_type');
        });
    }
};
