<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use Illuminate\Foundation\Http\FormRequest;

final class PurchaseCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_invoice_id' => ['required', 'uuid'],
            'supplier_credit_invoice_number' => ['required', 'string', 'max:512'],
            'supplier_credit_date' => ['required', 'date_format:Y-m-d'],
            'received_date' => ['required', 'date_format:Y-m-d'],
            'source_line_ids' => ['required', 'array', 'min:1'],
            'source_line_ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
