<?php

use App\Infrastructure\Persistence\OpenItemControlAccountBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $mappings = (new OpenItemControlAccountBackfill(DB::connection()))->mappings();

        Schema::table('open_items', function (Blueprint $table): void {
            $table->uuid('control_ledger_account_id')->nullable()->after('journal_entry_id');
        });

        foreach ($mappings as $openItemId => $ledgerAccountId) {
            DB::table('open_items')->where('id', $openItemId)->update([
                'control_ledger_account_id' => $ledgerAccountId,
            ]);
        }

        if (DB::table('open_items')->whereNull('control_ledger_account_id')->exists()) {
            throw new RuntimeException('Every OpenItem must have a factual control LedgerAccount before the column becomes required.');
        }

        Schema::table('open_items', function (Blueprint $table): void {
            $table->uuid('control_ledger_account_id')->nullable(false)->change();
            $table->foreign(
                ['administration_id', 'control_ledger_account_id'],
                'oi_control_account_tenant_fk',
            )->references(['administration_id', 'id'])->on('ledger_accounts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('open_items', function (Blueprint $table): void {
            $table->dropForeign('oi_control_account_tenant_fk');
            $table->dropColumn('control_ledger_account_id');
        });
    }
};
