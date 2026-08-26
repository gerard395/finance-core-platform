<?php

declare(strict_types=1);

namespace App\Http\Requests\Administration;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateAdministrationBankAccountRequest extends FormRequest
{
    public function rules(): array
    {
        return ['account_holder' => ['required', 'string', 'min:2', 'max:255'], 'label' => ['required', 'string', 'min:2', 'max:100']];
    }
}
