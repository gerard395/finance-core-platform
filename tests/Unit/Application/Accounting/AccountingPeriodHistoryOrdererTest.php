<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Accounting;

use App\Application\Accounting\AccountingPeriodHistoryEntry;
use App\Application\Accounting\AccountingPeriodHistoryIntegrityException;
use App\Application\Accounting\AccountingPeriodHistoryOrderer;
use App\Domain\Accounting\Enums\AccountingPeriodStatus;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AccountingPeriodHistoryOrdererTest extends TestCase
{
    #[Test]
    public function it_orders_same_timestamp_transitions_by_causality_in_either_input_order(): void
    {
        $close = $this->entry(AccountingPeriodStatus::Open, AccountingPeriodStatus::Closed, 'close', '2026-09-01 10:00:00');
        $reopen = $this->entry(AccountingPeriodStatus::Closed, AccountingPeriodStatus::Open, 'reopen', '2026-09-01 10:00:00');

        foreach ([[$reopen, $close], [$close, $reopen]] as $input) {
            $ordered = (new AccountingPeriodHistoryOrderer)->order($input, AccountingPeriodStatus::Open);
            self::assertSame(['close', 'reopen'], array_map(static fn ($entry) => $entry->reason, $ordered));
        }
    }

    #[Test]
    public function it_orders_multiple_timestamp_groups_and_a_unique_same_timestamp_chain(): void
    {
        $history = [
            $this->entry(AccountingPeriodStatus::Closed, AccountingPeriodStatus::Open, 'reopen-2', '2026-09-02 10:00:00'),
            $this->entry(AccountingPeriodStatus::Closed, AccountingPeriodStatus::Open, 'reopen-1', '2026-09-01 10:00:00'),
            $this->entry(AccountingPeriodStatus::Open, AccountingPeriodStatus::Closed, 'close-2', '2026-09-02 10:00:00'),
            $this->entry(AccountingPeriodStatus::Open, AccountingPeriodStatus::Closed, 'close-1', '2026-09-01 09:00:00'),
        ];

        $ordered = (new AccountingPeriodHistoryOrderer)->order($history, AccountingPeriodStatus::Open);

        self::assertSame(['close-1', 'reopen-1', 'close-2', 'reopen-2'], array_map(static fn ($entry) => $entry->reason, $ordered));
    }

    #[Test]
    public function it_rejects_an_ambiguous_or_broken_chain_instead_of_using_input_identity(): void
    {
        $this->expectException(AccountingPeriodHistoryIntegrityException::class);

        (new AccountingPeriodHistoryOrderer)->order([
            $this->entry(AccountingPeriodStatus::Open, AccountingPeriodStatus::Closed, 'close-a', '2026-09-01 10:00:00'),
            $this->entry(AccountingPeriodStatus::Open, AccountingPeriodStatus::Closed, 'close-b', '2026-09-01 10:00:00'),
        ], AccountingPeriodStatus::Closed);
    }

    #[Test]
    public function it_rejects_a_chain_that_does_not_match_the_current_period_status(): void
    {
        $this->expectException(AccountingPeriodHistoryIntegrityException::class);

        (new AccountingPeriodHistoryOrderer)->order([
            $this->entry(AccountingPeriodStatus::Open, AccountingPeriodStatus::Closed, 'close', '2026-09-01 10:00:00'),
        ], AccountingPeriodStatus::Open);
    }

    private function entry(AccountingPeriodStatus $from, AccountingPeriodStatus $to, string $reason, string $at): AccountingPeriodHistoryEntry
    {
        return new AccountingPeriodHistoryEntry(
            $from,
            $to,
            $reason,
            new UserId(new Uuid('a9150000-0000-4000-8000-000000000001')),
            new DateTimeImmutable($at),
        );
    }
}
