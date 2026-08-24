<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

final class StoreSalesInvoiceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'uuid'],
            'invoice_date' => ['required', 'date_format:Y-m-d'],
            'due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:invoice_date'],
        ];
    }
}
