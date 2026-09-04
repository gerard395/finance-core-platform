<?php

declare(strict_types=1);

namespace App\Http\Requests\Banking;

use App\Domain\Banking\Enums\BankEntryReconciliationIntent;
use App\Http\Requests\Concerns\FlashesOnlyAllowlistedInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReconcileBankEntryRequest extends FormRequest
{
    use FlashesOnlyAllowlistedInput;

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
            'intent' => ['required', Rule::enum(BankEntryReconciliationIntent::class)],
            'posting_date' => ['required', 'date_format:Y-m-d'],
            'relation_id' => ['nullable', 'required_unless:intent,other', 'uuid'],
            'allocations' => ['array', 'required_unless:intent,other'],
            'allocations.*.open_item_id' => ['required', 'uuid', 'distinct'],
            'allocations.*.amount' => ['required', 'decimal:0,8', 'gt:0'],
            'contra_ledger_account_id' => ['nullable', 'required_if:intent,other', 'uuid'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function flashInputKeys(): array
    {
        return ['intent', 'posting_date', 'relation_id', 'allocations', 'contra_ledger_account_id', 'description'];
    }
}
