<?php

declare(strict_types=1);

namespace App\Http\Requests\Administration;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateSalesPostingConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'sales_journal_id' => ['required', 'uuid'],
            'accounts_receivable_ledger_account_id' => ['required', 'uuid'],
            'revenue_ledger_account_id' => ['required', 'uuid'],
            'output_vat_ledger_account_id' => ['required', 'uuid'],
        ];
    }
}
