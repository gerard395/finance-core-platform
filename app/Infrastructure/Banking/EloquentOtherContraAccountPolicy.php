<?php

declare(strict_types=1);

namespace App\Infrastructure\Banking;

use App\Application\Banking\OtherContraAccountPolicy;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use Illuminate\Support\Facades\DB;

final class EloquentOtherContraAccountPolicy implements OtherContraAccountPolicy
{
    public function isAllowed(AdministrationId $administrationId, LedgerAccountId $accountId): bool
    {
        $admin = $administrationId->toString();
        $id = $accountId->toString();
        if (! DB::table('ledger_accounts')->where('administration_id', $admin)->where('id', $id)->where('status', 'active')->exists()) {
            return false;
        }
        $protected = DB::table('banking_posting_configurations')->where('administration_id', $admin)->pluck('bank_ledger_account_id')->all();
        foreach (DB::table('sales_posting_configurations')->where('administration_id', $admin)->get(['accounts_receivable_ledger_account_id', 'output_vat_ledger_account_id']) as $row) {
            $protected[] = $row->accounts_receivable_ledger_account_id;
            $protected[] = $row->output_vat_ledger_account_id;
        }
        foreach (DB::table('purchase_posting_configurations')->where('administration_id', $admin)->get(['accounts_payable_ledger_account_id', 'input_vat_ledger_account_id', 'vat_payable_ledger_account_id']) as $row) {
            $protected[] = $row->accounts_payable_ledger_account_id;
            $protected[] = $row->input_vat_ledger_account_id;
            if ($row->vat_payable_ledger_account_id !== null) {
                $protected[] = $row->vat_payable_ledger_account_id;
            }
        }
        $protected = [...$protected, ...DB::table('open_items')->where('administration_id', $admin)->distinct()->pluck('control_ledger_account_id')->all()];

        return ! in_array($id, $protected, true);
    }
}
