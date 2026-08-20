<?php

declare(strict_types=1);

namespace App\Http\Requests\Relations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateRelationRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['name.not_regex' => 'De naam mag niet met witruimte beginnen of eindigen.'];
    }
}
