<?php

declare(strict_types=1);

namespace App\Http\Requests\Relations;

use Illuminate\Foundation\Http\FormRequest;

final class StoreRelationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:2', 'max:32', 'regex:/\A[A-Za-z0-9][A-Za-z0-9_-]{1,31}\z/'],
            'name' => ['required', 'string', 'min:2', 'max:255', 'not_regex:/\A\s|\s\z/u'],
            'vat_identification_number' => ['nullable', 'string', 'max:32', 'regex:/\A\s*[A-Za-z0-9][A-Za-z0-9.-]*\s*\z/'],
            'fiscal_jurisdiction' => ['nullable', 'string', 'size:2', 'alpha:ascii'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['vat_identification_number', 'fiscal_jurisdiction'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.regex' => 'De code mag alleen letters, cijfers, een streepje en een liggend streepje bevatten.',
            'name.not_regex' => 'De naam mag niet met witruimte beginnen of eindigen.',
        ];
    }
}
