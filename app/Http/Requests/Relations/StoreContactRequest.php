<?php

declare(strict_types=1);

namespace App\Http\Requests\Relations;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255', 'not_regex:/\A\s|\s\z/u'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'min:3', 'max:32', 'regex:/\A[+0-9][0-9 ()+.-]*[0-9]\z/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => $this->emptyToNull($this->input('email')),
            'phone' => $this->emptyToNull($this->input('phone')),
        ]);
    }

    private function emptyToNull(mixed $value): mixed
    {
        return is_string($value) && trim($value) === '' ? null : $value;
    }
}
