<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Domain\Accounting\Enums\JournalType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class CreateJournalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['code' => ['required', 'string', 'min:2', 'max:16'], 'name' => ['required', 'string', 'min:2', 'max:255'], 'type' => ['required', new Enum(JournalType::class)]];
    }
}
