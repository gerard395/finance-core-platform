<?php

declare(strict_types=1);

namespace App\Http\Requests\Relations;

use App\Domain\Relations\Enums\AddressType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(AddressType::class)],
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
