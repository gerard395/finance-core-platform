<?php

declare(strict_types=1);

namespace App\Http\Requests\Relations;

use Illuminate\Foundation\Http\FormRequest;

final class StoreBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'account_name' => ['required', 'string', 'min:2', 'max:255', 'not_regex:/\A\s|\s\z/u'],
            'iban' => ['required', 'string', 'min:15', 'max:34', 'regex:/\A[A-Za-z]{2}[0-9]{2}[A-Za-z0-9]{11,30}\z/'],
            'bic' => ['required', 'string', 'regex:/\A[A-Za-z]{6}[A-Za-z0-9]{2}(?:[A-Za-z0-9]{3})?\z/'],
        ];
    }
}
