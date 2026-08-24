<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

final class StoreSalesCreditInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'source_invoice_id' => ['required', 'uuid'],
            'credit_date' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
