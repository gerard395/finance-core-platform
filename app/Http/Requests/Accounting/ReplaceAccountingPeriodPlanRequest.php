<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

final class ReplaceAccountingPeriodPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expected_period_ids' => ['required', 'array'],
            'expected_period_ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
