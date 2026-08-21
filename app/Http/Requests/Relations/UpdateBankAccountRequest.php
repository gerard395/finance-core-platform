<?php

declare(strict_types=1);

namespace App\Http\Requests\Relations;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['account_name' => ['required', 'string', 'min:2', 'max:255', 'not_regex:/\A\s|\s\z/u']];
    }
}
