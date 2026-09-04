<?php

declare(strict_types=1);

namespace App\Http\Requests\Banking;

use App\Http\Requests\Concerns\FlashesOnlyAllowlistedInput;
use Illuminate\Foundation\Http\FormRequest;

final class ConfirmBankImportRequest extends FormRequest
{
    use FlashesOnlyAllowlistedInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['preview_token' => ['required', 'string', 'size:64', 'regex:/\A[a-f0-9]+\z/']];
    }

    protected function flashInputKeys(): array
    {
        return [];
    }
}
