<?php

declare(strict_types=1);

namespace App\Http\Requests\Administration;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateSalesDocumentMasterDataRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'display_name' => ['nullable', 'required_with:legal_name,registration_number,iban,bic', 'string', 'min:2', 'max:255', 'not_regex:/[\r\n]/'],
            'legal_name' => ['nullable', 'string', 'min:2', 'max:255', 'not_regex:/[\r\n]/'],
            'registration_number' => ['nullable', 'string', 'max:64', 'regex:/\A[A-Za-z0-9][A-Za-z0-9 .-]*\z/'],
            'address_line_1' => ['nullable', 'string', 'min:2', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'min:2', 'max:255'],
            'postal_code' => ['nullable', 'string', 'min:2', 'max:16'],
            'city' => ['nullable', 'string', 'min:2', 'max:255'],
            'country_code' => ['nullable', 'string', 'size:2', 'alpha:ascii'],
            'business_email' => ['nullable', 'email:rfc', 'max:255'],
            'business_phone' => ['nullable', 'string', 'max:32', 'not_regex:/[\r\n]/'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'iban' => ['nullable', 'string', 'max:34', 'regex:/\A[A-Za-z]{2}[0-9]{2}[A-Za-z0-9]{11,30}\z/'],
            'bic' => ['nullable', 'string', 'max:11', 'regex:/\A[A-Za-z]{6}[A-Za-z0-9]{2}(?:[A-Za-z0-9]{3})?\z/'],
            'account_holder' => ['nullable', 'string', 'min:2', 'max:255', 'not_regex:/[\r\n]/'],
            'sender_name' => ['nullable', 'string', 'min:2', 'max:255', 'not_regex:/[\r\n]/'],
            'sender_email' => ['nullable', 'email:rfc', 'max:255'],
            'reply_to_email' => ['nullable', 'email:rfc', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (array_keys($this->rules()) as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
        foreach (['country_code', 'iban', 'bic'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => strtoupper(str_replace(' ', '', $this->input($field)))]);
            }
        }
    }
}
