<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

final class StoreQuotationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'uuid'],
            'quotation_date' => ['required', 'date_format:Y-m-d'],
            'expiry_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
