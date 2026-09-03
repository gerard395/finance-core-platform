<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Banking;

use App\Application\Banking\BankEntrySuggestionEngine;
use App\Application\Banking\BankEntrySuggestionIntent;
use App\Application\Banking\BankEntrySuggestionOutcome;
use App\Application\Banking\BankReconciliationCandidate;
use App\Domain\Accounting\Enums\OpenItemSide;
use App\Domain\Accounting\Enums\OpenItemType;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Banking\Entities\BankStatementEntry;
use App\Domain\Banking\Enums\BankEntryDirection;
use App\Domain\Banking\ValueObjects\BankStatementEntryId;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class BankEntrySuggestionEngineTest extends TestCase
{
    public function test_exact_customer_reference_and_amount_prepare_receipt_without_mutation(): void
    {
        $candidate = $this->candidate(1, 1, '100', 'INV-2026-001', OpenItemType::Receivable, OpenItemSide::Debit);
        $result = (new BankEntrySuggestionEngine)->suggest($this->entry('100', BankEntryDirection::Credit, 'Betaling INV-2026-001'), [$candidate]);

        self::assertSame(BankEntrySuggestionIntent::CustomerReceipt, $result->intent);
        self::assertSame(BankEntrySuggestionOutcome::PaymentReady, $result->outcome);
        self::assertSame('100', $result->allocations[0]->amount->amount());
        self::assertContains('exact_document_reference', $result->evidence);
        self::assertContains('exact_open_amount', $result->evidence);
    }

    public function test_supplier_payment_can_prepare_multiple_same_relation_allocations(): void
    {
        $items = [
            $this->candidate(1, 1, '60', 'SUP-1', OpenItemType::Payable, OpenItemSide::Credit),
            $this->candidate(2, 1, '40', 'SUP-2', OpenItemType::Payable, OpenItemSide::Credit),
        ];
        $result = (new BankEntrySuggestionEngine)->suggest($this->entry('-100', BankEntryDirection::Debit, 'SUP-1 SUP-2'), $items);

        self::assertSame(BankEntrySuggestionIntent::SupplierPayment, $result->intent);
        self::assertSame(BankEntrySuggestionOutcome::PaymentReady, $result->outcome);
        self::assertCount(2, $result->allocations);
        self::assertSame(['60', '40'], array_map(static fn ($allocation): string => $allocation->amount->amount(), $result->allocations));
        self::assertCount(1, array_unique(array_map(static fn ($allocation): string => $allocation->relationId->toString(), $result->allocations)));
    }

    public function test_partial_open_item_is_payment_ready_and_preserves_balance_snapshot(): void
    {
        $result = (new BankEntrySuggestionEngine)->suggest($this->entry('40', BankEntryDirection::Credit, 'INV-PART'), [$this->candidate(1, 1, '100', 'INV-PART', OpenItemType::Receivable, OpenItemSide::Debit)]);
        self::assertSame(BankEntrySuggestionOutcome::PaymentReady, $result->outcome);
        self::assertSame('40', $result->allocations[0]->amount->amount());
        self::assertSame('100', $result->allocations[0]->currentOpenBalance->amount());
    }

    public function test_equal_strength_cross_relation_candidates_are_ambiguous(): void
    {
        $result = (new BankEntrySuggestionEngine)->suggest($this->entry('100', BankEntryDirection::Credit, 'UNKNOWN'), [
            $this->candidate(1, 1, '100', 'A', OpenItemType::Receivable, OpenItemSide::Debit),
            $this->candidate(2, 2, '100', 'B', OpenItemType::Receivable, OpenItemSide::Debit),
        ]);
        self::assertSame(BankEntrySuggestionOutcome::Ambiguous, $result->outcome);
        self::assertNull($result->relationId);
        self::assertCount(2, $result->ambiguousRelations);
        self::assertSame([], $result->allocations);
    }

    public function test_overpayment_is_not_payment_ready(): void
    {
        $result = (new BankEntrySuggestionEngine)->suggest($this->entry('-125', BankEntryDirection::Debit, 'SUP'), [$this->candidate(1, 1, '100', 'SUP', OpenItemType::Payable, OpenItemSide::Credit)]);
        self::assertSame(BankEntrySuggestionOutcome::AllocationIncomplete, $result->outcome);
        self::assertSame('100', $result->allocations[0]->amount->amount());
    }

    public function test_wrong_side_or_closed_balance_produces_other_boundary(): void
    {
        $result = (new BankEntrySuggestionEngine)->suggest($this->entry('100', BankEntryDirection::Credit, 'SUP'), [$this->candidate(1, 1, '100', 'SUP', OpenItemType::Payable, OpenItemSide::Credit)]);
        self::assertSame(BankEntrySuggestionIntent::Other, $result->intent);
        self::assertSame(BankEntrySuggestionOutcome::Other, $result->outcome);
        self::assertSame([], $result->allocations);
    }

    public function test_suggestions_are_reproducible_and_do_not_reserve_open_items(): void
    {
        $engine = new BankEntrySuggestionEngine;
        $candidate = $this->candidate(1, 1, '100', 'INV', OpenItemType::Receivable, OpenItemSide::Debit);
        $first = $engine->suggest($this->entry('60', BankEntryDirection::Credit, 'INV'), [$candidate]);
        $second = $engine->suggest($this->entry('60', BankEntryDirection::Credit, 'INV'), [$candidate]);
        self::assertEquals($first, $second);
        self::assertSame('100', $candidate->openBalance->amount());
    }

    private function entry(string $amount, BankEntryDirection $direction, string $remittance): BankStatementEntry
    {
        return new BankStatementEntry($this->entryId(50), new DateTimeImmutable('2026-09-03'), null, new Money($amount, new Currency('EUR')), $direction, false, null, null, null, null, null, [$remittance], null, null, null, null, null, null, [], 1);
    }

    private function candidate(int $item, int $relation, string $open, string $reference, OpenItemType $type, OpenItemSide $side): BankReconciliationCandidate
    {
        return new BankReconciliationCandidate(new OpenItemId(new Uuid($this->uuid($item))), new RelationId(new Uuid($this->uuid(100 + $relation))), 'Relation '.$relation, $type, $side, new LedgerAccountId(new Uuid($this->uuid(200 + $item))), new Money($open, new Currency('EUR')), new DateTimeImmutable('2026-08-01'), new DateTimeImmutable('2026-09-10'), $reference, []);
    }

    private function entryId(int $number): BankStatementEntryId
    {
        return new BankStatementEntryId(new Uuid($this->uuid($number)));
    }

    private function uuid(int $number): string
    {
        return sprintf('%08x-0000-4000-8000-%012x', $number, $number);
    }
}
