<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Fiscal\TaxPostingReadRepository;
use App\Application\Fiscal\TaxPostingStore;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Entities\TaxPosting;
use App\Domain\Fiscal\Enums\IcpClassification;
use App\Domain\Fiscal\Enums\SupplierVatMode;
use App\Domain\Fiscal\Enums\TaxLegRole;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\Enums\TaxPostingType;
use App\Domain\Fiscal\Enums\TaxReportingClassification;
use App\Domain\Fiscal\Enums\TaxSourceDocumentType;
use App\Domain\Fiscal\Enums\TaxTreatment;
use App\Domain\Fiscal\Enums\TaxTreatmentType;
use App\Domain\Fiscal\Enums\VatReturnClassification;
use App\Domain\Fiscal\ValueObjects\DeductibilityBasisPoints;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Fiscal\ValueObjects\TaxPostingId;
use App\Domain\Fiscal\ValueObjects\TaxPostingLegSnapshot;
use App\Domain\Fiscal\ValueObjects\TaxRate;
use App\Domain\Fiscal\ValueObjects\TaxSourceDocumentId;
use App\Domain\Fiscal\ValueObjects\TaxSourceLineId;
use App\Domain\Fiscal\ValueObjects\TaxTreatmentDefinitionId;
use App\Domain\Fiscal\ValueObjects\TaxTreatmentGroupId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\JournalEntryLineRecord;
use App\Infrastructure\Persistence\Eloquent\Models\JournalEntryRecord;
use App\Infrastructure\Persistence\Eloquent\Models\TaxPostingRecord;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\QueryException;

final class EloquentTaxPostingRepository implements TaxPostingReadRepository, TaxPostingStore
{
    public function findForTreatmentGroup(AdministrationId $administrationId, TaxTreatmentGroupId $groupId): array
    {
        return TaxPostingRecord::query()
            ->where('administration_id', $administrationId->toString())
            ->where('tax_treatment_group_id', $groupId->toString())
            ->orderBy('tax_leg_role')
            ->orderBy('id')
            ->get()
            ->map(static fn (TaxPostingRecord $record): TaxPosting => self::hydrate($record))
            ->all();
    }

    public function hasReversalForOriginalSource(AdministrationId $administrationId, TaxSourceDocumentType $sourceDocumentType, TaxSourceDocumentId $sourceDocumentId): bool
    {
        return TaxPostingRecord::query()
            ->from('tax_postings as reversal')
            ->join('tax_postings as original', function ($join): void {
                $join->on('original.administration_id', '=', 'reversal.administration_id')
                    ->on('original.id', '=', 'reversal.reversed_tax_posting_id');
            })
            ->where('original.administration_id', $administrationId->toString())
            ->where('original.source_document_type', $sourceDocumentType->value)
            ->where('original.source_document_id', $sourceDocumentId->toString())
            ->where('original.type', TaxPostingType::Original->value)
            ->where('reversal.type', TaxPostingType::Reversal->value)
            ->exists();
    }

    public function findOriginalsForSource(AdministrationId $administrationId, TaxSourceDocumentType $sourceDocumentType, TaxSourceDocumentId $sourceDocumentId): array
    {
        return TaxPostingRecord::query()
            ->where('administration_id', $administrationId->toString())
            ->where('source_document_type', $sourceDocumentType->value)
            ->where('source_document_id', $sourceDocumentId->toString())
            ->where('type', TaxPostingType::Original->value)
            ->orderBy('source_line_id')
            ->get()
            ->map(static fn (TaxPostingRecord $record): TaxPosting => self::hydrate($record))
            ->all();
    }

    public function findForSource(AdministrationId $administrationId, TaxSourceDocumentType $sourceDocumentType, TaxSourceDocumentId $sourceDocumentId): array
    {
        return TaxPostingRecord::query()
            ->where('administration_id', $administrationId->toString())
            ->where('source_document_type', $sourceDocumentType->value)
            ->where('source_document_id', $sourceDocumentId->toString())
            ->orderBy('source_line_id')
            ->orderBy('tax_leg_role')
            ->orderBy('id')
            ->get()
            ->map(static fn (TaxPostingRecord $record): TaxPosting => self::hydrate($record))
            ->all();
    }

    public function findForAdministrationAndPeriod(
        AdministrationId $administrationId,
        PostingDate $startDate,
        PostingDate $endDate,
    ): array {
        if ($startDate->value() > $endDate->value()) {
            throw new DomainException('Tax posting read start date cannot be after end date.');
        }

        return TaxPostingRecord::query()
            ->where('administration_id', $administrationId->toString())
            ->whereBetween('posting_date', [
                $startDate->value()->format('Y-m-d'),
                $endDate->value()->format('Y-m-d'),
            ])
            ->orderBy('posting_date')
            ->orderBy('id')
            ->get()
            ->map(static fn (TaxPostingRecord $record): TaxPosting => self::hydrate($record))
            ->all();
    }

    public function append(TaxPosting $taxPosting): void
    {
        if (TaxPostingRecord::query()->whereKey($taxPosting->id()->toString())->exists()) {
            throw new DomainException('A TaxPosting with this identity already exists.');
        }

        $this->assertAccountingReferences($taxPosting);

        if ($taxPosting->type() === TaxPostingType::Reversal) {
            $target = TaxPostingRecord::query()
                ->whereKey($taxPosting->reversedTaxPostingId()?->toString())
                ->where('administration_id', $taxPosting->administrationId()->toString())
                ->where('type', TaxPostingType::Original->value)
                ->first();

            if ($target === null) {
                throw new DomainException('A TaxPosting reversal must reference an Original in the same Administration.');
            }
        }

        $snapshot = $taxPosting->legSnapshot();
        $attributes = [
            'id' => $taxPosting->id()->toString(),
            'administration_id' => $taxPosting->administrationId()->toString(),
            'tax_code_id' => $taxPosting->taxCodeId()->toString(),
            'tax_rate' => $taxPosting->taxRate()->value(),
            'taxable_base' => $taxPosting->taxableBase()->amount(),
            'tax_amount' => $taxPosting->taxAmount()->amount(),
            'currency' => $taxPosting->taxableBase()->currency()->code(),
            'direction' => $taxPosting->direction()->value,
            'type' => $taxPosting->type()->value,
            'source_document_type' => $taxPosting->sourceDocumentType()->value,
            'source_document_id' => $taxPosting->sourceDocumentId()->toString(),
            'source_line_id' => $taxPosting->sourceLineId()->toString(),
            'posting_date' => $taxPosting->postingDate()->value()->format('Y-m-d'),
            'journal_entry_id' => $taxPosting->journalEntryId()->toString(),
            'base_journal_entry_line_id' => $taxPosting->baseJournalEntryLineId()->toString(),
            'tax_journal_entry_line_id' => $taxPosting->taxJournalEntryLineId()?->toString(),
            'reversed_tax_posting_id' => $taxPosting->reversedTaxPostingId()?->toString(),
            'treatment' => $taxPosting->treatment()->value,
            'vat_return_classification' => $taxPosting->vatReturnClassification()->value,
            'icp_classification' => $taxPosting->icpClassification()->value,
            'tax_treatment_definition_id' => $snapshot?->definitionId->toString(),
            'tax_treatment_definition_version' => $snapshot?->definitionVersion,
            'tax_treatment_group_id' => $snapshot?->groupId->toString(),
            'tax_leg_role' => $snapshot?->role->value,
            'treatment_type' => $snapshot?->treatmentType->value,
            'tax_jurisdiction' => $snapshot?->jurisdiction,
            'reporting_classification' => $snapshot?->reportingClassification->value,
            'deductibility_basis_points' => $snapshot?->deductibility->value(),
            'assessed_vat' => $snapshot?->assessedVat->amount(),
            'deductible_vat' => $snapshot?->deductibleVat->amount(),
            'non_deductible_tax_cost' => $snapshot?->nonDeductibleTaxCost->amount(),
            'supplier_vat_mode' => $snapshot?->supplierVatMode->value,
        ];

        try {
            TaxPostingRecord::query()->create($attributes);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062
                && str_contains($exception->getMessage(), 'tp_treatment_group_role_unique')) {
                throw new DomainException('This tax treatment group already contains the tax leg role for its source.', previous: $exception);
            }

            throw $exception;
        }
    }

    private function assertAccountingReferences(TaxPosting $taxPosting): void
    {
        $administration = $taxPosting->administrationId()->toString();
        $entry = $taxPosting->journalEntryId()->toString();
        $postedEntryExists = JournalEntryRecord::query()
            ->whereKey($entry)
            ->where('administration_id', $administration)
            ->where('status', 'posted')
            ->exists();

        if (! $postedEntryExists || ! $this->lineExists($administration, $entry, $taxPosting->baseJournalEntryLineId())) {
            throw new DomainException('TaxPosting Accounting references must belong to a Posted JournalEntry in the same Administration.');
        }

        $taxLine = $taxPosting->taxJournalEntryLineId();

        if ($taxLine !== null && ! $this->lineExists($administration, $entry, $taxLine)) {
            throw new DomainException('TaxPosting Accounting references must belong to a Posted JournalEntry in the same Administration.');
        }
    }

    private function lineExists(string $administration, string $entry, JournalEntryLineId $lineId): bool
    {
        return JournalEntryLineRecord::query()
            ->whereKey($lineId->toString())
            ->where('administration_id', $administration)
            ->where('journal_entry_id', $entry)
            ->exists();
    }

    private static function hydrate(TaxPostingRecord $record): TaxPosting
    {
        $currency = new Currency($record->getAttribute('currency'));
        $taxLineId = $record->getAttribute('tax_journal_entry_line_id');
        $reversedId = $record->getAttribute('reversed_tax_posting_id');
        $groupId = $record->getAttribute('tax_treatment_group_id');
        $legSnapshot = $groupId === null ? null : new TaxPostingLegSnapshot(
            new TaxTreatmentDefinitionId(new Uuid($record->getAttribute('tax_treatment_definition_id'))),
            (int) $record->getAttribute('tax_treatment_definition_version'),
            new TaxTreatmentGroupId(new Uuid($groupId)),
            TaxLegRole::from($record->getAttribute('tax_leg_role')),
            TaxTreatmentType::from($record->getAttribute('treatment_type')),
            $record->getAttribute('tax_jurisdiction'),
            TaxReportingClassification::from($record->getAttribute('reporting_classification')),
            new DeductibilityBasisPoints((int) $record->getAttribute('deductibility_basis_points')),
            new Money((string) $record->getAttribute('assessed_vat'), $currency),
            new Money((string) $record->getAttribute('deductible_vat'), $currency),
            new Money((string) $record->getAttribute('non_deductible_tax_cost'), $currency),
            SupplierVatMode::from($record->getAttribute('supplier_vat_mode')),
        );

        return new TaxPosting(
            new TaxPostingId(new Uuid($record->getAttribute('id'))),
            new AdministrationId(new Uuid($record->getAttribute('administration_id'))),
            new TaxCodeId(new Uuid($record->getAttribute('tax_code_id'))),
            new TaxRate((string) $record->getAttribute('tax_rate')),
            new Money((string) $record->getAttribute('taxable_base'), $currency),
            new Money((string) $record->getAttribute('tax_amount'), $currency),
            TaxPostingDirection::from($record->getAttribute('direction')),
            TaxSourceDocumentType::from($record->getAttribute('source_document_type')),
            new TaxSourceDocumentId(new Uuid($record->getAttribute('source_document_id'))),
            new TaxSourceLineId(new Uuid($record->getAttribute('source_line_id'))),
            new PostingDate(new DateTimeImmutable($record->getAttribute('posting_date')->format('Y-m-d'))),
            new JournalEntryId(new Uuid($record->getAttribute('journal_entry_id'))),
            new JournalEntryLineId(new Uuid($record->getAttribute('base_journal_entry_line_id'))),
            $taxLineId === null ? null : new JournalEntryLineId(new Uuid($taxLineId)),
            TaxPostingType::from($record->getAttribute('type')),
            $reversedId === null ? null : new TaxPostingId(new Uuid($reversedId)),
            TaxTreatment::from($record->getAttribute('treatment')),
            VatReturnClassification::from($record->getAttribute('vat_return_classification')),
            IcpClassification::from($record->getAttribute('icp_classification')),
            $legSnapshot,
        );
    }
}
