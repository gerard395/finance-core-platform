<?php

declare(strict_types=1);

namespace App\Http\Requests\Administration;

use Illuminate\Foundation\Http\FormRequest;

final class UpdatePurchasePostingConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'purchase_journal_id' => ['required', 'uuid'],
            'accounts_payable_ledger_account_id' => ['required', 'uuid'],
            'input_vat_ledger_account_id' => ['required', 'uuid'],
        ];
    }
}
