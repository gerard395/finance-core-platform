<?php

declare(strict_types=1);

namespace App\Http\Requests\Relations;

use App\Application\Sales\SalesDocumentRecipientPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SetSalesDocumentRecipientPreferenceRequest extends FormRequest
{
    public function rules(): array
    {
        return ['purpose' => ['required', Rule::enum(SalesDocumentRecipientPurpose::class)], 'contact_id' => ['required', 'uuid']];
    }
}
