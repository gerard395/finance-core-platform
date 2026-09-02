<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use App\Application\Fiscal\TaxTreatmentDefinitionRepository;
use App\Domain\Fiscal\Enums\DeductibilityPolicy;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final class PurchaseInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines', []);
        if (is_array($lines)) {
            foreach ($lines as $index => $line) {
                if (is_array($line) && is_string($line['deductibility_rationale'] ?? null)) {
                    $lines[$index]['deductibility_rationale'] = trim($line['deductibility_rationale']);
                }
            }
        }

        $this->merge(['currency' => 'EUR', 'lines' => $lines]);
    }

    public function rules(TaxTreatmentDefinitionRepository $treatments): array
    {
        $rules = [
            'supplier_id' => ['required', 'uuid'],
            'supplier_invoice_number' => ['required', 'string', 'max:512'],
            'invoice_date' => ['required', 'date_format:Y-m-d'],
            'received_date' => ['required', 'date_format:Y-m-d'],
            'supply_date' => ['nullable', 'date_format:Y-m-d'],
            'due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:invoice_date'],
            'currency' => ['required', Rule::in(['EUR'])],
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:32'],
            'city' => ['required', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'size:2'],
            'lines' => ['required', 'array', 'min:1', 'max:20'],
            'lines.*.description' => ['nullable', 'string', 'max:1000'],
            'lines.*.quantity' => ['nullable', 'regex:/\A(?:0*[1-9]\d*)(?:\.\d{1,8})?\z/'],
            'lines.*.unit_price' => ['nullable', 'regex:/\A\d+(?:\.\d{1,8})?\z/'],
            'lines.*.ledger_account_id' => ['nullable', 'uuid'],
            'lines.*.tax_code_id' => ['nullable', 'uuid'],
            'lines.*.fully_deductible' => ['nullable', 'boolean'],
            'lines.*.international' => ['nullable', 'boolean'],
            'lines.*.supply_classification' => ['nullable', Rule::in(['goods', 'general_rule_service'])],
            'lines.*.business_to_business' => ['nullable', 'boolean'],
            'lines.*.arrives_in_netherlands' => ['nullable', 'boolean'],
            'lines.*.general_rule_confirmed' => ['nullable', 'boolean'],
            'lines.*.special_place_of_supply' => ['nullable', 'boolean'],
            'lines.*.foreign_supplier_vat' => ['nullable', 'boolean'],
            'lines.*.import_or_customs' => ['nullable', 'boolean'],
            'lines.*.evidence' => ['nullable', 'string', 'max:1000'],
            'lines.*.deductibility_percentage' => ['nullable', 'integer', 'between:0,100'],
            'lines.*.deductibility_rationale' => ['nullable', 'string', 'max:1000'],
            'lines.*._delete' => ['nullable', 'boolean'],
        ];

        foreach ((array) $this->input('lines', []) as $index => $line) {
            if (is_array($line) && $this->requiresDeductibilityRationale($line, $treatments)) {
                $rules["lines.$index.deductibility_rationale"] = ['required', 'string', 'max:1000'];
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'lines.*.deductibility_rationale.required' => 'Vul de onderbouwing voor het aftrekpercentage in.',
            'lines.*.deductibility_rationale.string' => 'De onderbouwing voor het aftrekpercentage is ongeldig.',
            'lines.*.deductibility_rationale.max' => 'De onderbouwing voor het aftrekpercentage mag maximaal 1000 tekens bevatten.',
        ];
    }

    private function requiresDeductibilityRationale(array $line, TaxTreatmentDefinitionRepository $treatments): bool
    {
        if (! filter_var($line['international'] ?? false, FILTER_VALIDATE_BOOL)) {
            return false;
        }

        $context = $this->attributes->get('administration_context');
        if (! $context instanceof ActiveAdministrationContext || ! is_string($line['tax_code_id'] ?? null)) {
            return false;
        }

        try {
            $taxCodeId = new TaxCodeId(new Uuid($line['tax_code_id']));
        } catch (InvalidArgumentException) {
            return false;
        }

        $selection = $treatments->resolveActiveForTaxCode($context->administration->id(), $taxCodeId);

        return $selection->definition?->deductibilityPolicy() === DeductibilityPolicy::UserSpecifiedLineRate;
    }
}
