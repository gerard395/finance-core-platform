<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Accounting\Entities;

use App\Domain\Accounting\Entities\OpenItem;
use App\Domain\Accounting\Entities\OpenItemSettlement;
use App\Domain\Accounting\Enums\OpenItemSettlementType;
use App\Domain\Accounting\Enums\OpenItemStatus;
use App\Domain\Accounting\Enums\OpenItemType;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Accounting\ValueObjects\OpenItemSettlementId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;

final class OpenItemTest extends TestCase
{
    public function test_new_open_item_exposes_immutable_opening_context_and_is_fully_open(): void
    {
        $openedOn = $this->date('2026-01-01');
        $item = $this->createOpenItem('1000', $openedOn);

        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $item->id()->toString());
        self::assertSame('123e4567-e89b-42d3-a456-426614174000', $item->administrationId()->toString());
        self::assertSame('936da01f-9abd-4d9d-80c7-02af85c822a8', $item->relationId()->toString());
        self::assertSame('6ba7b810-9dad-41d1-80b4-00c04fd430c8', $item->journalEntryId()->toString());
        self::assertSame(OpenItemType::Receivable, $item->type());
        self::assertSame($openedOn, $item->openedOn());
        self::assertSame('1000', $item->originalAmount()->amount());
        self::assertSame('1000', $item->openAmount()->amount());
        self::assertSame(OpenItemStatus::Open, $item->status());
        self::assertSame([], $item->settlements());
        self::assertTrue($item->isOpen());
    }

    public function test_historical_open_amount_and_status_follow_applied_settlements(): void
    {
        $item = $this->createOpenItem('1000', $this->date('2026-01-01'));
        $firstId = $this->settlementId(1);
        $secondId = $this->settlementId(2);

        $item->applySettlement($firstId, $this->date('2026-01-15'), $this->money('400'), $this->journalEntryId(1));
        $historyBeforeQueries = $item->settlements();

        self::assertSame('1000', $item->openAmountAt($this->date('2026-01-10'))->amount());
        self::assertSame('600', $item->openAmountAt($this->date('2026-01-20'))->amount());
        self::assertSame(OpenItemStatus::Open, $item->statusAt($this->date('2026-01-10')));
        self::assertSame(OpenItemStatus::PartiallySettled, $item->statusAt($this->date('2026-01-20')));
        self::assertTrue($item->isPartiallySettled());
        self::assertSame($historyBeforeQueries, $item->settlements());
        self::assertSame($firstId, $historyBeforeQueries[0]->id());
        self::assertSame('2026-01-15', $historyBeforeQueries[0]->effectiveDate()->value()->format('Y-m-d'));
        self::assertSame('400', $historyBeforeQueries[0]->amount()->amount());
        self::assertSame($this->journalEntryId(1)->toString(), $historyBeforeQueries[0]->sourceJournalEntryId()->toString());
        self::assertSame(OpenItemSettlementType::Applied, $historyBeforeQueries[0]->type());

        $item->applySettlement($secondId, $this->date('2026-02-10'), $this->money('600'), $this->journalEntryId(2));

        self::assertSame('600', $item->openAmountAt($this->date('2026-01-31'))->amount());
        self::assertTrue($item->openAmountAt($this->date('2026-02-28'))->isZero());
        self::assertSame(OpenItemStatus::Closed, $item->statusAt($this->date('2026-02-28')));
        self::assertTrue($item->openAmount()->isZero());
        self::assertTrue($item->isClosed());
    }

    public function test_payable_type_remains_immutable_through_settlement_lifecycle(): void
    {
        $item = $this->createOpenItem(type: OpenItemType::Payable);
        $appliedId = $this->settlementId(1);

        $item->applySettlement($appliedId, $this->date('2026-01-15'), $this->money('100'), $this->journalEntryId(1));
        self::assertSame(OpenItemType::Payable, $item->type());
        self::assertSame(OpenItemStatus::Closed, $item->status());

        $item->reverseSettlement($this->settlementId(2), $this->date('2026-01-16'), $appliedId, $this->journalEntryId(2));
        self::assertSame(OpenItemType::Payable, $item->type());
        self::assertSame(OpenItemStatus::Open, $item->status());
    }

    public function test_full_reversal_reopens_and_does_not_mutate_applied_settlement(): void
    {
        $item = $this->createOpenItem('1000', $this->date('2026-01-01'));
        $appliedId = $this->settlementId(1);
        $reversalId = $this->settlementId(2);
        $item->applySettlement($appliedId, $this->date('2026-01-15'), $this->money('1000'), $this->journalEntryId(1));
        $applied = $item->settlement($appliedId);

        $item->reverseSettlement($reversalId, $this->date('2026-02-01'), $appliedId, $this->journalEntryId(2));

        self::assertNotNull($applied);
        self::assertSame($applied, $item->settlement($appliedId));
        self::assertSame(OpenItemSettlementType::Applied, $applied->type());
        self::assertNull($applied->reversedSettlementId());
        self::assertSame('1000', $item->settlement($reversalId)?->amount()->amount());
        self::assertTrue($item->settlement($reversalId)?->reversedSettlementId()?->equals($appliedId));
        self::assertSame(OpenItemStatus::Closed, $item->statusAt($this->date('2026-01-31')));
        self::assertSame(OpenItemStatus::Open, $item->statusAt($this->date('2026-02-01')));
        self::assertTrue($item->isOpen());
    }

    public function test_settlement_lookup_and_history_are_append_only_and_deterministically_ordered(): void
    {
        $item = $this->createOpenItem();
        $laterId = $this->settlementId(2);
        $earlierId = $this->settlementId(1);
        $sameDateHigherId = $this->settlementId(3);

        $item->applySettlement($laterId, $this->date('2026-01-20'), $this->money('10'), $this->journalEntryId(2));
        $item->applySettlement($sameDateHigherId, $this->date('2026-01-15'), $this->money('10'), $this->journalEntryId(3));
        $item->applySettlement($earlierId, $this->date('2026-01-15'), $this->money('10'), $this->journalEntryId(1));

        $history = $item->settlements();
        self::assertSame([$earlierId, $sameDateHigherId, $laterId], array_map(
            static fn ($settlement) => $settlement->id(),
            $history,
        ));
        self::assertTrue($item->hasSettlement($earlierId));
        self::assertSame($history[0], $item->settlement($earlierId));
        self::assertNull($item->settlement($this->settlementId(9)));

        array_pop($history);
        self::assertCount(3, $item->settlements());
    }

    public function test_duplicate_settlement_id_is_rejected_without_mutation(): void
    {
        $item = $this->createOpenItem();
        $id = $this->settlementId(1);
        $item->applySettlement($id, $this->date('2026-01-15'), $this->money('10'), $this->journalEntryId(1));

        try {
            $item->applySettlement($id, $this->date('2026-01-16'), $this->money('10'), $this->journalEntryId(2));
            self::fail('A duplicate settlement ID must be rejected.');
        } catch (DomainException) {
            self::assertCount(1, $item->settlements());
            self::assertSame('90', $item->openAmount()->amount());
        }
    }

    public function test_settlement_before_opening_date_is_rejected(): void
    {
        $item = $this->createOpenItem();

        $this->expectException(DomainException::class);
        $item->applySettlement($this->settlementId(1), $this->date('2025-12-31'), $this->money('10'), $this->journalEntryId(1));
    }

    public function test_historical_query_before_opening_date_is_rejected(): void
    {
        $item = $this->createOpenItem();

        $this->expectException(DomainException::class);
        $item->openAmountAt($this->date('2025-12-31'));
    }

    public function test_zero_and_negative_settlement_amounts_are_rejected(): void
    {
        foreach (['0', '-1'] as $index => $amount) {
            $item = $this->createOpenItem();

            try {
                $item->applySettlement($this->settlementId($index + 1), $this->date('2026-01-15'), $this->money($amount), $this->journalEntryId(1));
                self::fail('A non-positive settlement must be rejected.');
            } catch (DomainException) {
                self::assertSame([], $item->settlements());
            }
        }
    }

    public function test_currency_mismatch_is_rejected(): void
    {
        $item = $this->createOpenItem();

        $this->expectException(DomainException::class);
        $item->applySettlement(
            $this->settlementId(1),
            $this->date('2026-01-15'),
            new Money('10', new Currency('USD')),
            $this->journalEntryId(1),
        );
    }

    public function test_over_settlement_is_rejected_without_mutation(): void
    {
        $item = $this->createOpenItem();

        $this->expectException(DomainException::class);
        $item->applySettlement($this->settlementId(1), $this->date('2026-01-15'), $this->money('100.00000001'), $this->journalEntryId(1));
    }

    public function test_backdated_settlement_that_invalidates_later_history_is_rejected(): void
    {
        $item = $this->createOpenItem();
        $item->applySettlement($this->settlementId(1), $this->date('2026-02-01'), $this->money('60'), $this->journalEntryId(1));

        try {
            $item->applySettlement($this->settlementId(2), $this->date('2026-01-15'), $this->money('50'), $this->journalEntryId(2));
            self::fail('History cannot contain a negative chronological balance.');
        } catch (DomainException) {
            self::assertCount(1, $item->settlements());
            self::assertSame('40', $item->openAmount()->amount());
        }
    }

    public function test_unknown_settlement_cannot_be_reversed(): void
    {
        $item = $this->createOpenItem();

        $this->expectException(DomainException::class);
        $item->reverseSettlement($this->settlementId(2), $this->date('2026-02-01'), $this->settlementId(1), $this->journalEntryId(2));
    }

    public function test_applied_settlement_cannot_be_reversed_twice(): void
    {
        $item = $this->createOpenItem();
        $appliedId = $this->settlementId(1);
        $item->applySettlement($appliedId, $this->date('2026-01-15'), $this->money('40'), $this->journalEntryId(1));
        $item->reverseSettlement($this->settlementId(2), $this->date('2026-02-01'), $appliedId, $this->journalEntryId(2));

        $this->expectException(DomainException::class);
        $item->reverseSettlement($this->settlementId(3), $this->date('2026-02-02'), $appliedId, $this->journalEntryId(3));
    }

    public function test_reversal_cannot_precede_the_applied_settlement_in_history(): void
    {
        $item = $this->createOpenItem();
        $appliedId = $this->settlementId(1);
        $item->applySettlement($appliedId, $this->date('2026-02-01'), $this->money('40'), $this->journalEntryId(1));

        $this->expectException(DomainException::class);
        $item->reverseSettlement($this->settlementId(2), $this->date('2026-01-15'), $appliedId, $this->journalEntryId(2));
    }

    public function test_original_amount_must_be_positive(): void
    {
        $this->expectException(DomainException::class);
        $this->createOpenItem('0');
    }

    public function test_it_reconstitutes_empty_and_complete_factual_state(): void
    {
        $original = $this->createOpenItem('1000', $this->date('2026-01-01'));
        $item = OpenItem::reconstitute(
            $original->id(),
            $original->administrationId(),
            $original->relationId(),
            $original->journalEntryId(),
            OpenItemType::Payable,
            $original->originalAmount(),
            $original->openedOn(),
            [],
        );

        self::assertSame($original->id(), $item->id());
        self::assertSame($original->administrationId(), $item->administrationId());
        self::assertSame($original->relationId(), $item->relationId());
        self::assertSame($original->journalEntryId(), $item->journalEntryId());
        self::assertSame(OpenItemType::Payable, $item->type());
        self::assertSame($original->originalAmount(), $item->originalAmount());
        self::assertSame($original->openedOn(), $item->openedOn());
        self::assertSame('EUR', $item->originalAmount()->currency()->code());
        self::assertSame([], $item->settlements());
        self::assertSame(OpenItemStatus::Open, $item->status());
    }

    public function test_it_reconstitutes_ordered_historical_amounts_and_statuses(): void
    {
        $first = $this->settlement(1, '2026-01-15', '400');
        $second = $this->settlement(2, '2026-02-10', '600');
        $input = [$second, $first];
        $item = $this->reconstitute($input, '1000');

        self::assertSame([$second, $first], $input);
        self::assertSame([$first, $second], $item->settlements());
        self::assertSame('1000', $item->openAmountAt($this->date('2026-01-10'))->amount());
        self::assertSame(OpenItemStatus::Open, $item->statusAt($this->date('2026-01-10')));
        self::assertSame('600', $item->openAmountAt($this->date('2026-01-20'))->amount());
        self::assertSame(OpenItemStatus::PartiallySettled, $item->statusAt($this->date('2026-01-20')));
        self::assertSame('600', $item->openAmountAt($this->date('2026-01-31'))->amount());
        self::assertSame('0', $item->openAmountAt($this->date('2026-02-28'))->amount());
        self::assertSame(OpenItemStatus::Closed, $item->statusAt($this->date('2026-02-28')));
    }

    public function test_reconstitution_orders_same_date_by_settlement_identity_and_restores_reversal(): void
    {
        $applied = $this->settlement(1, '2026-01-15', '40');
        $other = $this->settlement(2, '2026-01-15', '10');
        $reversal = $this->settlement(3, '2026-02-01', '40', OpenItemSettlementType::Reversal, $applied->id());
        $item = $this->reconstitute([$reversal, $other, $applied]);

        self::assertSame([$applied, $other, $reversal], $item->settlements());
        self::assertSame('50', $item->openAmountAt($this->date('2026-01-31'))->amount());
        self::assertSame('90', $item->openAmountAt($this->date('2026-02-01'))->amount());
        self::assertSame($applied->id(), $item->settlement($reversal->id())?->reversedSettlementId());

        $item->applySettlement($this->settlementId(4), $this->date('2026-02-02'), $this->money('10'), $this->journalEntryId(4));
        self::assertSame('80', $item->openAmount()->amount());
    }

    public function test_reconstitution_rejects_duplicate_identity_currency_and_date_corruption(): void
    {
        $settlement = $this->settlement(1, '2026-01-15', '10');

        foreach ([
            [$settlement, $settlement],
            [$this->settlement(1, '2026-01-15', '10', currency: 'USD')],
            [$this->settlement(1, '2025-12-31', '10')],
        ] as $history) {
            try {
                $this->reconstitute($history);
                self::fail('Corrupt settlement history must be rejected.');
            } catch (DomainException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_reconstitution_rejects_unknown_and_reversal_targets(): void
    {
        $unknown = $this->settlement(2, '2026-02-01', '10', OpenItemSettlementType::Reversal, $this->settlementId(1));

        try {
            $this->reconstitute([$unknown]);
            self::fail('Unknown reversal target must be rejected.');
        } catch (DomainException) {
            self::assertTrue(true);
        }

        $applied = $this->settlement(1, '2026-01-10', '10');
        $reversal = $this->settlement(2, '2026-01-11', '10', OpenItemSettlementType::Reversal, $applied->id());
        $targetsReversal = $this->settlement(3, '2026-01-12', '10', OpenItemSettlementType::Reversal, $reversal->id());

        $this->expectException(DomainException::class);
        $this->reconstitute([$applied, $reversal, $targetsReversal]);
    }

    public function test_reconstitution_rejects_duplicate_reversal_and_amount_mismatch(): void
    {
        $applied = $this->settlement(1, '2026-01-10', '40');
        $reversal = $this->settlement(2, '2026-01-11', '40', OpenItemSettlementType::Reversal, $applied->id());
        $duplicate = $this->settlement(3, '2026-01-12', '40', OpenItemSettlementType::Reversal, $applied->id());

        try {
            $this->reconstitute([$applied, $reversal, $duplicate]);
            self::fail('Duplicate reversal must be rejected.');
        } catch (DomainException) {
            self::assertTrue(true);
        }

        $this->expectException(DomainException::class);
        $this->reconstitute([
            $applied,
            $this->settlement(2, '2026-01-11', '39', OpenItemSettlementType::Reversal, $applied->id()),
        ]);
    }

    public function test_reconstitution_rejects_chronological_over_settlement(): void
    {
        $this->expectException(DomainException::class);
        $this->reconstitute([
            $this->settlement(2, '2026-02-01', '60'),
            $this->settlement(1, '2026-01-15', '50'),
        ]);
    }

    private function createOpenItem(
        string $originalAmount = '100',
        ?PostingDate $openedOn = null,
        OpenItemType $type = OpenItemType::Receivable,
    ): OpenItem {
        return new OpenItem(
            new OpenItemId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new AdministrationId(new Uuid('123e4567-e89b-42d3-a456-426614174000')),
            new RelationId(new Uuid('936da01f-9abd-4d9d-80c7-02af85c822a8')),
            new JournalEntryId(new Uuid('6ba7b810-9dad-41d1-80b4-00c04fd430c8')),
            $type,
            $this->money($originalAmount),
            $openedOn ?? $this->date('2026-01-01'),
        );
    }

    private function money(string $amount): Money
    {
        return new Money($amount, new Currency('EUR'));
    }

    private function date(string $date): PostingDate
    {
        return new PostingDate(new DateTimeImmutable($date));
    }

    private function settlementId(int $suffix): OpenItemSettlementId
    {
        return new OpenItemSettlementId(new Uuid(sprintf('00000000-0000-4000-8000-%012d', $suffix)));
    }

    private function journalEntryId(int $suffix): JournalEntryId
    {
        return new JournalEntryId(new Uuid(sprintf('10000000-0000-4000-8000-%012d', $suffix)));
    }

    /** @param list<OpenItemSettlement> $settlements */
    private function reconstitute(array $settlements, string $originalAmount = '100'): OpenItem
    {
        $item = $this->createOpenItem($originalAmount, $this->date('2026-01-01'));

        return OpenItem::reconstitute(
            $item->id(),
            $item->administrationId(),
            $item->relationId(),
            $item->journalEntryId(),
            $item->type(),
            $item->originalAmount(),
            $item->openedOn(),
            $settlements,
        );
    }

    private function settlement(
        int $suffix,
        string $date,
        string $amount,
        OpenItemSettlementType $type = OpenItemSettlementType::Applied,
        ?OpenItemSettlementId $reversedSettlementId = null,
        string $currency = 'EUR',
    ): OpenItemSettlement {
        return new OpenItemSettlement(
            $this->settlementId($suffix),
            $this->date($date),
            new Money($amount, new Currency($currency)),
            $this->journalEntryId($suffix),
            $type,
            $reversedSettlementId,
        );
    }
}
