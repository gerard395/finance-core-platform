<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PurchaseInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['currency' => 'EUR']);
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'uuid'],
            'supplier_invoice_number' => ['required', 'string', 'max:512'],
            'invoice_date' => ['required', 'date_format:Y-m-d'],
            'received_date' => ['required', 'date_format:Y-m-d'],
            'supply_date' => ['nullable', 'date_format:Y-m-d'],
            'due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:invoice_date'],
            'currency' => ['required', Rule::in(['EUR'])],
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:32'],
            'city' => ['required', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'size:2'],
            'lines' => ['required', 'array', 'min:1', 'max:20'],
            'lines.*.description' => ['nullable', 'string', 'max:1000'],
            'lines.*.quantity' => ['nullable', 'regex:/\A(?:0*[1-9]\d*)(?:\.\d{1,8})?\z/'],
            'lines.*.unit_price' => ['nullable', 'regex:/\A\d+(?:\.\d{1,8})?\z/'],
            'lines.*.ledger_account_id' => ['nullable', 'uuid'],
            'lines.*.tax_code_id' => ['nullable', 'uuid'],
            'lines.*.fully_deductible' => ['nullable', 'boolean'],
            'lines.*._delete' => ['nullable', 'boolean'],
        ];
    }
}
