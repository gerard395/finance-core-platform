<?php

declare(strict_types=1);

namespace App\Http\Requests\Banking;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BankTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['allocations' => array_values(array_filter((array) $this->input('allocations', []), static fn ($row): bool => is_array($row) && ! empty($row['open_item_id'])))]);
    }

    public function rules(): array
    {
        return [
            'bank_account_id' => ['required', 'uuid'],
            'transaction_date' => ['required', 'date_format:Y-m-d'],
            'payment_type' => ['required', Rule::in(['customer_receipt', 'supplier_payment'])],
            'amount' => ['required', 'decimal:0,8', 'gt:0'],
            'relation_id' => ['required', 'uuid'],
            'reference' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1000'],
            'allocations' => ['array'],
            'allocations.*.allocation_id' => ['nullable', 'uuid'],
            'allocations.*.open_item_id' => ['required', 'uuid', 'distinct'],
            'allocations.*.amount' => ['required', 'decimal:0,8', 'gt:0'],
        ];
    }
}
