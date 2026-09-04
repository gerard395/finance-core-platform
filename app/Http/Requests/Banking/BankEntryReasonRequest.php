<?php

declare(strict_types=1);

namespace App\Http\Requests\Banking;

use App\Http\Requests\Concerns\FlashesOnlyAllowlistedInput;
use Illuminate\Foundation\Http\FormRequest;

final class BankEntryReasonRequest extends FormRequest
{
    use FlashesOnlyAllowlistedInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:500']];
    }

    protected function flashInputKeys(): array
    {
        return ['reason'];
    }
}
