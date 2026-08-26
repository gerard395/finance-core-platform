<?php

declare(strict_types=1);

namespace App\Http\Requests\Administration;

use Illuminate\Foundation\Http\FormRequest;

final class StoreAdministrationBankAccountRequest extends FormRequest
{
    public function rules(): array
    {
        return ['iban' => ['required', 'string', 'max:34'], 'bic' => ['nullable', 'string', 'max:11'], 'account_holder' => ['required', 'string', 'min:2', 'max:255'], 'label' => ['required', 'string', 'min:2', 'max:100']];
    }
}
