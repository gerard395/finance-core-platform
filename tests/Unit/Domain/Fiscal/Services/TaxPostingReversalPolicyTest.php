<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fiscal\Services;

use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Entities\TaxPosting;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\Enums\TaxPostingType;
use App\Domain\Fiscal\Enums\TaxSourceDocumentType;
use App\Domain\Fiscal\Services\TaxPostingReversalPolicy;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Fiscal\ValueObjects\TaxPostingId;
use App\Domain\Fiscal\ValueObjects\TaxRate;
use App\Domain\Fiscal\ValueObjects\TaxSourceDocumentId;
use App\Domain\Fiscal\ValueObjects\TaxSourceLineId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TaxPostingReversalPolicyTest extends TestCase
{
    public function test_valid_full_reversal_is_accepted_without_mutating_original(): void
    {
        $original = $this->posting();
        $reversal = $this->posting(
            id: 2,
            type: TaxPostingType::Reversal,
            reversedTaxPostingId: $original->id(),
            sourceDocumentType: TaxSourceDocumentType::SalesCreditInvoice,
        );

        (new TaxPostingReversalPolicy)->assertCanReverseOriginal($original, [$original]);
        (new TaxPostingReversalPolicy)->assertValidReversal($original, $reversal, [$original]);

        self::assertSame(TaxPostingType::Original, $original->type());
        self::assertNull($original->reversedTaxPostingId());
        self::assertSame($original->id(), $reversal->reversedTaxPostingId());
    }

    public function test_unknown_target_is_rejected(): void
    {
        $original = $this->posting();
        $this->expectException(DomainException::class);
        (new TaxPostingReversalPolicy)->assertCanReverseOriginal($original, []);
    }

    public function test_reversal_cannot_be_the_target(): void
    {
        $original = $this->posting();
        $firstReversal = $this->reversalOf($original);

        $this->expectException(DomainException::class);
        (new TaxPostingReversalPolicy)->assertCanReverseOriginal($firstReversal, [$original, $firstReversal]);
    }

    public function test_candidate_must_be_a_reversal(): void
    {
        $original = $this->posting();

        $this->expectException(DomainException::class);
        (new TaxPostingReversalPolicy)->assertValidReversal($original, $this->posting(id: 2), [$original]);
    }

    #[DataProvider('mismatchedReversals')]
    public function test_snapshot_and_context_mismatches_are_rejected(string $mismatch): void
    {
        $original = $this->posting();
        $arguments = [
            'id' => 2,
            'type' => TaxPostingType::Reversal,
            'reversedTaxPostingId' => $original->id(),
        ];

        match ($mismatch) {
            'direction' => $arguments['direction'] = TaxPostingDirection::Input,
            'tax code' => $arguments['taxCode'] = 2,
            'tax rate' => $arguments['taxRate'] = '9',
            'taxable base' => $arguments['taxableBase'] = '101',
            'tax amount' => $arguments['taxAmount'] = '22',
            'currency' => $arguments['currency'] = 'USD',
            'administration' => $arguments['administration'] = 2,
        };

        $this->expectException(DomainException::class);
        (new TaxPostingReversalPolicy)->assertValidReversal(
            $original,
            $this->posting(...$arguments),
            [$original],
        );
    }

    /** @return array<string, array{string}> */
    public static function mismatchedReversals(): array
    {
        return [
            'direction mismatch' => ['direction'],
            'TaxCode mismatch' => ['tax code'],
            'TaxRate mismatch' => ['tax rate'],
            'taxableBase mismatch' => ['taxable base'],
            'taxAmount mismatch' => ['tax amount'],
            'Currency mismatch' => ['currency'],
            'Administration mismatch' => ['administration'],
        ];
    }

    public function test_second_reversal_of_same_original_is_rejected(): void
    {
        $original = $this->posting();
        $firstReversal = $this->reversalOf($original);

        $this->expectException(DomainException::class);
        (new TaxPostingReversalPolicy)->assertCanReverseOriginal(
            $original,
            [$original, $firstReversal],
        );
    }

    public function test_preflight_requires_only_original_and_existing_history(): void
    {
        $original = $this->posting();

        (new TaxPostingReversalPolicy)->assertCanReverseOriginal($original, [$original]);

        self::assertSame(TaxPostingType::Original, $original->type());
    }

    public function test_reversal_must_reference_supplied_original(): void
    {
        $original = $this->posting();
        $otherOriginal = $this->posting(id: 2);
        $reversal = $this->posting(
            id: 3,
            type: TaxPostingType::Reversal,
            reversedTaxPostingId: $otherOriginal->id(),
        );

        $this->expectException(DomainException::class);
        (new TaxPostingReversalPolicy)->assertValidReversal($original, $reversal, [$original, $otherOriginal]);
    }

    private function reversalOf(TaxPosting $original): TaxPosting
    {
        return $this->posting(
            id: 2,
            type: TaxPostingType::Reversal,
            reversedTaxPostingId: $original->id(),
        );
    }

    private function posting(
        int $id = 1,
        TaxPostingType $type = TaxPostingType::Original,
        ?TaxPostingId $reversedTaxPostingId = null,
        TaxPostingDirection $direction = TaxPostingDirection::Output,
        TaxSourceDocumentType $sourceDocumentType = TaxSourceDocumentType::SalesInvoice,
        int $taxCode = 1,
        string $taxRate = '21',
        string $taxableBase = '100',
        string $taxAmount = '21',
        string $currency = 'EUR',
        int $administration = 1,
    ): TaxPosting {
        $moneyCurrency = new Currency($currency);

        return new TaxPosting(
            $this->taxPostingId($id),
            new AdministrationId($this->uuid('2', $administration)),
            new TaxCodeId($this->uuid('3', $taxCode)),
            new TaxRate($taxRate),
            new Money($taxableBase, $moneyCurrency),
            new Money($taxAmount, $moneyCurrency),
            $direction,
            $sourceDocumentType,
            new TaxSourceDocumentId($this->uuid('4', $id)),
            new TaxSourceLineId($this->uuid('5', $id)),
            new PostingDate(new DateTimeImmutable('2026-08-19')),
            new JournalEntryId($this->uuid('6', $id)),
            new JournalEntryLineId($this->uuid('7', $id)),
            new JournalEntryLineId($this->uuid('8', $id)),
            $type,
            $reversedTaxPostingId,
        );
    }

    private function taxPostingId(int $suffix): TaxPostingId
    {
        return new TaxPostingId($this->uuid('1', $suffix));
    }

    private function uuid(string $prefix, int $suffix): Uuid
    {
        return new Uuid(sprintf('%s0000000-0000-4000-8000-%012d', $prefix, $suffix));
    }
}
