<?php

declare(strict_types=1);

namespace App\Http\Requests\Relations;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'address_line_1' => ['required', 'string', 'min:2', 'max:255', 'not_regex:/\A\s|\s\z/u'],
            'address_line_2' => ['nullable', 'string', 'min:2', 'max:255', 'not_regex:/\A\s|\s\z/u'],
            'postal_code' => ['required', 'string', 'min:2', 'max:16', 'regex:/\A[A-Za-z0-9](?:[A-Za-z0-9 -]{0,14}[A-Za-z0-9])?\z/'],
            'city' => ['required', 'string', 'min:2', 'max:255', 'not_regex:/\A\s|\s\z/u'],
            'country_code' => ['required', 'string', 'regex:/\A[A-Za-z]{2}\z/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $line2 = $this->input('address_line_2');
        $this->merge(['address_line_2' => is_string($line2) && trim($line2) === '' ? null : $line2]);
    }
}
