<?php

declare(strict_types=1);

namespace App\Http\Requests\Administration;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateBankingPostingConfigurationRequest extends FormRequest
{
    public function rules(): array
    {
        return ['bank_journal_id' => ['required', 'uuid'], 'bank_ledger_account_id' => ['required', 'uuid']];
    }
}
