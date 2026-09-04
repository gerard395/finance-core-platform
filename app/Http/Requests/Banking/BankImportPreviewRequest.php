<?php

declare(strict_types=1);

namespace App\Http\Requests\Banking;

use App\Http\Requests\Concerns\FlashesOnlyAllowlistedInput;
use Illuminate\Foundation\Http\FormRequest;

final class BankImportPreviewRequest extends FormRequest
{
    use FlashesOnlyAllowlistedInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_account_id' => ['required', 'uuid'],
            'file' => ['required', 'file', 'max:5120'],
        ];
    }

    protected function flashInputKeys(): array
    {
        return ['bank_account_id'];
    }
}
