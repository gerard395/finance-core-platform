<?php

declare(strict_types=1);

namespace App\Http\Requests\Administration;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateAdministrationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255', 'not_regex:/\A\s|\s\z/u'],
            'description' => ['nullable', 'string', 'max:1000', 'not_regex:/\A\s|\s\z/u'],
            'vat_identification_number' => ['nullable', 'string', 'max:32', 'regex:/\A\s*[A-Za-z0-9][A-Za-z0-9.-]*\s*\z/'],
            'fiscal_jurisdiction' => ['nullable', 'string', 'size:2', 'alpha:ascii'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('description') === '') {
            $this->merge(['description' => null]);
        }
        foreach (['vat_identification_number', 'fiscal_jurisdiction'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }
}
