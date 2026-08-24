<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateQuotationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'quotation_date' => ['required', 'date_format:Y-m-d'],
            'expiry_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
