<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Banking;

use App\Application\Banking\BankEntryFinancialHistoryOrderer;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\BankEntryReconciliation;
use App\Domain\Banking\Enums\BankEntryReconciliationIntent;
use App\Domain\Banking\ValueObjects\BankEntryReconciliationId;
use App\Domain\Banking\ValueObjects\BankStatementEntryId;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class BankEntryFinancialHistoryOrdererTest extends TestCase
{
    public function test_same_timestamp_and_adversarial_uuid_order_use_causal_ancestry(): void
    {
        $first = $this->entry('ffffffff-ffff-4fff-8fff-ffffffffffff');
        $second = $this->entry('00000000-0000-4000-8000-000000000001', $first->id);
        $third = $this->entry('80000000-0000-4000-8000-000000000001', $second->id);

        $ordered = (new BankEntryFinancialHistoryOrderer)->order([$third, $second, $first]);

        self::assertSame([$first, $second, $third], $ordered);
    }

    public function test_ambiguous_roots_branches_cycles_and_disconnected_ancestry_fail_closed(): void
    {
        $orderer = new BankEntryFinancialHistoryOrderer;
        $root = $this->entry('10000000-0000-4000-8000-000000000001');
        $child = $this->entry('20000000-0000-4000-8000-000000000001', $root->id);
        self::assertNull($orderer->order([$root, $this->entry('30000000-0000-4000-8000-000000000001')]));
        self::assertNull($orderer->order([$root, $child, $this->entry('40000000-0000-4000-8000-000000000001', $root->id)]));
        self::assertNull($orderer->order([$this->entry('50000000-0000-4000-8000-000000000001', new BankEntryReconciliationId(new Uuid('60000000-0000-4000-8000-000000000001'))), $this->entry('60000000-0000-4000-8000-000000000001', new BankEntryReconciliationId(new Uuid('50000000-0000-4000-8000-000000000001')))]));
        self::assertNull($orderer->order([$root, $this->entry('70000000-0000-4000-8000-000000000001', new BankEntryReconciliationId(new Uuid('90000000-0000-4000-8000-000000000001')))]));
    }

    private function entry(string $id, ?BankEntryReconciliationId $replaces = null): BankEntryReconciliation
    {
        return new BankEntryReconciliation(new BankEntryReconciliationId(new Uuid($id)), new AdministrationId(new Uuid('a8100000-0000-4000-8000-000000000001')), new BankStatementEntryId(new Uuid('a8500000-0000-4000-8000-000000000001')), new BankTransactionId(new Uuid('b8500000-0000-4000-8000-000000000001')), BankEntryReconciliationIntent::Other, new DateTimeImmutable('2026-09-03'), new PostingDate(new DateTimeImmutable('2026-09-03')), new UserId(new Uuid('a8200000-0000-4000-8000-000000000001')), new DateTimeImmutable('2026-09-03 12:00:00'), $replaces);
    }
}
