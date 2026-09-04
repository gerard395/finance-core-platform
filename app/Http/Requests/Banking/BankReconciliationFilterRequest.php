<?php

declare(strict_types=1);

namespace App\Http\Requests\Banking;

use App\Application\Banking\BankEntryDerivedState;
use App\Domain\Banking\Enums\BankEntryDirection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BankReconciliationFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_account_id' => ['nullable', 'uuid'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'direction' => ['nullable', Rule::enum(BankEntryDirection::class)],
            'state' => ['nullable', Rule::enum(BankEntryDerivedState::class)],
            'amount' => ['nullable', 'decimal:0,8', 'not_in:0'],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
