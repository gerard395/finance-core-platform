<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

final class OrderLineRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'min:2', 'max:1000', 'regex:/\A\S(?:.*\S)?\z/us'],
            'quantity' => ['required', 'string', 'regex:/\A(?:0|[1-9][0-9]*)(?:\.[0-9]{1,8})?\z/', 'not_in:0'],
            'unit_price' => ['required', 'string', 'regex:/\A(?:0|[1-9][0-9]*)(?:\.[0-9]{1,8})?\z/'],
        ];
    }
}
