<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'order_date' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
