<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

final class StoreOrderSalesInvoiceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'draft_request_token' => ['required', 'string', 'max:2048'],
            'invoice_date' => ['required', 'date_format:Y-m-d'],
            'due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:invoice_date'],
            'supply_date' => ['nullable', 'date_format:Y-m-d'],
            'invoice_address_id' => ['required', 'uuid'],
            'lines' => ['required', 'array'],
            'lines.*' => ['required', 'array'],
            'lines.*.quantity' => ['nullable', 'string', 'regex:/\A(?:0|[1-9][0-9]*)(?:\.[0-9]{1,8})?\z/'],
            'lines.*.tax_code_id' => ['nullable', 'uuid'],
        ];
    }
}
