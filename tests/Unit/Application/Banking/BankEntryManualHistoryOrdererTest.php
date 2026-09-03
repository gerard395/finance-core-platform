<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Banking;

use App\Application\Banking\BankEntryManualHistoryIntegrityException;
use App\Application\Banking\BankEntryManualHistoryOrderer;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\BankEntryReconciliationHistory;
use App\Domain\Banking\Enums\BankEntryManualAction;
use App\Domain\Banking\ValueObjects\BankEntryReconciliationHistoryId;
use App\Domain\Banking\ValueObjects\BankStatementEntryId;
use App\Domain\Banking\ValueObjects\ReconciliationReason;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BankEntryManualHistoryOrdererTest extends TestCase
{
    public function test_sequence_and_predecessor_chain_are_authoritative_when_timestamps_match(): void
    {
        $ignore = $this->event(1, BankEntryManualAction::Ignore, null, 10);
        $restore = $this->event(2, BankEntryManualAction::RestoreFromIgnored, $ignore->id, 11);
        $ordered = (new BankEntryManualHistoryOrderer)->order([$restore, $ignore]);
        self::assertSame([$ignore, $restore], $ordered);
    }

    #[DataProvider('corruptHistories')]
    public function test_corrupt_or_ambiguous_chains_have_typed_integrity_behavior(array $history): void
    {
        $this->expectException(BankEntryManualHistoryIntegrityException::class);
        (new BankEntryManualHistoryOrderer)->order($history);
    }

    public static function corruptHistories(): iterable
    {
        $ignore = self::makeEvent(1, BankEntryManualAction::Ignore, null, 10);

        yield 'first event is restore' => [[self::makeEvent(1, BankEntryManualAction::RestoreFromIgnored, null, 10)]];
        yield 'broken predecessor' => [[$ignore, self::makeEvent(2, BankEntryManualAction::RestoreFromIgnored, null, 11)]];
        yield 'invalid repeated action' => [[$ignore, self::makeEvent(2, BankEntryManualAction::Ignore, $ignore->id, 11)]];
        yield 'duplicate sequence' => [[$ignore, self::makeEvent(2, BankEntryManualAction::RestoreFromIgnored, $ignore->id, 10)]];
    }

    private function event(int $number, BankEntryManualAction $action, ?BankEntryReconciliationHistoryId $predecessor, int $sequence): BankEntryReconciliationHistory
    {
        return self::makeEvent($number, $action, $predecessor, $sequence);
    }

    private static function makeEvent(int $number, BankEntryManualAction $action, ?BankEntryReconciliationHistoryId $predecessor, int $sequence): BankEntryReconciliationHistory
    {
        return new BankEntryReconciliationHistory(
            new BankEntryReconciliationHistoryId(new Uuid(sprintf('%08x-0000-4000-8000-%012x', $number, $number))),
            new AdministrationId(new Uuid('a8100000-0000-4000-8000-000000000001')),
            new BankStatementEntryId(new Uuid('a8500000-0000-4000-8000-000000000001')),
            $action,
            $predecessor,
            new ReconciliationReason('reason'),
            new UserId(new Uuid('a8200000-0000-4000-8000-000000000001')),
            new DateTimeImmutable('2026-09-03 10:00:00'),
            $sequence,
        );
    }
}
