<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Accounting;

use App\Application\Accounting\MatchOpenItems;
use App\Application\Accounting\MatchOpenItemsResult;
use App\Application\Accounting\MatchOpenItemsStatus;
use App\Application\Accounting\OpenItemReadRepository;
use App\Application\Accounting\OpenItemSettlementStore;
use App\Application\Accounting\OpenItemStore;
use App\Domain\Accounting\Entities\OpenItem;
use App\Domain\Accounting\Enums\JournalEntryStatus;
use App\Domain\Accounting\Enums\OpenItemSide;
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
use App\Infrastructure\Persistence\Eloquent\Models\AdministrationRecord;
use App\Infrastructure\Persistence\Eloquent\Models\JournalEntryRecord;
use App\Infrastructure\Persistence\Eloquent\Models\JournalRecord;
use App\Infrastructure\Persistence\Eloquent\Models\OpenItemMatchRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RelationRecord;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class MatchOpenItemsTest extends TestCase
{
    use RefreshDatabase;

    private const string ADMIN_A = '10000000-0000-4000-8000-000000000001';

    private const string ADMIN_B = '20000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->administration(self::ADMIN_A, 'A');
        $this->administration(self::ADMIN_B, 'B');
        $this->relation(self::ADMIN_A, 1);
        $this->relation(self::ADMIN_A, 2);
        $this->relation(self::ADMIN_B, 3);
        foreach ([self::ADMIN_A, self::ADMIN_B] as $administration) {
            for ($sequence = 1; $sequence <= 4; $sequence++) {
                $this->postedEntry($administration, $sequence);
            }
        }
    }

    public function test_fully_open_invoice_and_credit_are_closed_by_one_append_only_match(): void
    {
        [$debit, $credit] = $this->pair('121', '121');

        $result = $this->matchAvailable($debit, $credit);
        [$readDebit, $readCredit] = $this->readPair($debit, $credit);

        self::assertSame(MatchOpenItemsStatus::Success, $result->status);
        self::assertNotNull($result->matchId);
        self::assertTrue($readDebit->openAmount()->isZero());
        self::assertTrue($readCredit->openAmount()->isZero());
        self::assertTrue($readDebit->isClosed());
        self::assertTrue($readCredit->isClosed());
        self::assertCount(1, $readDebit->matches());
        self::assertSame(1, OpenItemMatchRecord::query()->count());
    }

    public function test_partially_paid_invoice_leaves_customer_credit_remainder(): void
    {
        [$debit, $credit] = $this->pair('121', '121');
        $debit->applySettlement($this->settlementId(1), $this->date('2026-01-05'), $this->money('40'), $this->entryId(self::ADMIN_A, 3));
        $settlement = $debit->settlement($this->settlementId(1));
        self::assertNotNull($settlement);
        $this->app->make(OpenItemSettlementStore::class)->appendSettlement($debit, $settlement);

        self::assertSame(MatchOpenItemsStatus::Success, $this->matchAvailable($debit, $credit)->status);
        self::assertSame(MatchOpenItemsStatus::NothingToMatch, $this->matchAvailable($debit, $credit)->status);
        [$readDebit, $readCredit] = $this->readPair($debit, $credit);

        self::assertSame('0', $readDebit->openAmount()->amount());
        self::assertSame('40', $readCredit->openAmount()->amount());
        self::assertSame(OpenItemType::Receivable, $readCredit->type());
        self::assertSame(OpenItemSide::Credit, $readCredit->side());
    }

    public function test_paid_invoice_remains_closed_and_full_customer_credit_stays_open(): void
    {
        [$debit, $credit] = $this->pair('121', '121');
        $debit->applySettlement($this->settlementId(1), $this->date('2026-01-05'), $this->money('121'), $this->entryId(self::ADMIN_A, 3));
        $settlement = $debit->settlement($this->settlementId(1));
        self::assertNotNull($settlement);
        $this->app->make(OpenItemSettlementStore::class)->appendSettlement($debit, $settlement);

        self::assertSame(MatchOpenItemsStatus::NothingToMatch, $this->matchAvailable($debit, $credit)->status);
        [$readDebit, $readCredit] = $this->readPair($debit, $credit);
        self::assertTrue($readDebit->isClosed());
        self::assertSame('121', $readCredit->openAmount()->amount());
        self::assertSame(0, OpenItemMatchRecord::query()->count());
    }

    public function test_matching_guards_tenant_relation_currency_side_amount_and_unknown_identity(): void
    {
        [$debit, $credit] = $this->pair('100', '100');
        $otherRelation = $this->item(3, '100', OpenItemSide::Credit, relation: 2);
        $otherCurrency = $this->item(4, '100', OpenItemSide::Credit, currency: 'USD');
        $otherDebit = $this->item(5, '100', OpenItemSide::Debit);
        $otherTenant = $this->item(6, '100', OpenItemSide::Credit, relation: 3, administration: self::ADMIN_B);
        foreach ([$otherRelation, $otherCurrency, $otherDebit, $otherTenant] as $item) {
            $this->app->make(OpenItemStore::class)->append($item);
        }

        self::assertSame(MatchOpenItemsStatus::InvalidMatch, $this->match($debit, $otherRelation, '10')->status);
        self::assertSame(MatchOpenItemsStatus::InvalidMatch, $this->match($debit, $otherCurrency, '10')->status);
        self::assertSame(MatchOpenItemsStatus::InvalidMatch, $this->match($debit, $otherDebit, '10')->status);
        self::assertSame(MatchOpenItemsStatus::NotFound, $this->match($debit, $otherTenant, '10')->status);
        self::assertSame(MatchOpenItemsStatus::InvalidMatch, $this->match($debit, $credit, '100.00000001')->status);
        self::assertSame(MatchOpenItemsStatus::InvalidMatch, $this->match($debit, $credit, '0')->status);
        $unknown = new OpenItemId(new Uuid('90000000-0000-4000-8000-000000000001'));
        self::assertSame(MatchOpenItemsStatus::NotFound, $this->app->make(MatchOpenItems::class)->execute($this->adminId(self::ADMIN_A), $debit->id(), $unknown, $this->money('10'), $this->date('2026-01-10'), $this->entryId(self::ADMIN_A, 4))->status);
        self::assertSame(0, OpenItemMatchRecord::query()->count());
    }

    public function test_match_changes_as_of_state_only_on_the_occurrence_date(): void
    {
        [$debit, $credit] = $this->pair('121', '121');
        self::assertSame(MatchOpenItemsStatus::Success, $this->match($debit, $credit, '81', '2026-01-10')->status);
        [$readDebit, $readCredit] = $this->readPair($debit, $credit);

        self::assertSame('121', $readDebit->openAmountAt($this->date('2026-01-09'))->amount());
        self::assertSame('121', $readCredit->openAmountAt($this->date('2026-01-09'))->amount());
        self::assertSame('40', $readDebit->openAmountAt($this->date('2026-01-10'))->amount());
        self::assertSame('40', $readCredit->openAmountAt($this->date('2026-01-10'))->amount());
    }

    public function test_concurrent_matching_cannot_overmatch(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required for the matching concurrency test.');
        }
        [$debit, $credit] = $this->pair('100', '100');
        DB::commit();
        $files = [tempnam(sys_get_temp_dir(), 'open-item-match-'), tempnam(sys_get_temp_dir(), 'open-item-match-')];
        $children = [];
        foreach ($files as $file) {
            self::assertIsString($file);
            $pid = pcntl_fork();
            self::assertNotSame(-1, $pid);
            if ($pid === 0) {
                DB::purge();
                $status = $this->match($debit, $credit, '80')->status;
                file_put_contents($file, $status->name);
                exit(0);
            }
            $children[] = $pid;
        }
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            self::assertTrue(pcntl_wifexited($status));
            self::assertSame(0, pcntl_wexitstatus($status));
        }
        $statuses = array_map(static fn (string $file): string => trim((string) file_get_contents($file)), $files);
        foreach ($files as $file) {
            unlink($file);
        }
        sort($statuses);
        self::assertSame(['InvalidMatch', 'Success'], $statuses);
        self::assertSame(1, OpenItemMatchRecord::query()->count());
        [$readDebit, $readCredit] = $this->readPair($debit, $credit);
        self::assertSame('20', $readDebit->openAmount()->amount());
        self::assertSame('20', $readCredit->openAmount()->amount());
        $this->removeCommittedConcurrencyFixture();
        DB::beginTransaction();
    }

    private function removeCommittedConcurrencyFixture(): void
    {
        OpenItemMatchRecord::query()->where('administration_id', self::ADMIN_A)->delete();
        DB::table('open_item_settlements')->where('administration_id', self::ADMIN_A)->delete();
        DB::table('open_items')->whereIn('administration_id', [self::ADMIN_A, self::ADMIN_B])->delete();
        JournalEntryRecord::query()->whereIn('administration_id', [self::ADMIN_A, self::ADMIN_B])->delete();
        JournalRecord::query()->whereIn('administration_id', [self::ADMIN_A, self::ADMIN_B])->delete();
        RelationRecord::query()->whereIn('administration_id', [self::ADMIN_A, self::ADMIN_B])->delete();
        AdministrationRecord::query()->whereIn('id', [self::ADMIN_A, self::ADMIN_B])->delete();
    }

    /** @return array{OpenItem, OpenItem} */
    private function pair(string $debitAmount, string $creditAmount): array
    {
        $debit = $this->item(1, $debitAmount, OpenItemSide::Debit);
        $credit = $this->item(2, $creditAmount, OpenItemSide::Credit);
        $this->app->make(OpenItemStore::class)->append($debit);
        $this->app->make(OpenItemStore::class)->append($credit);

        return [$debit, $credit];
    }

    private function item(int $sequence, string $amount, OpenItemSide $side, int $relation = 1, string $currency = 'EUR', string $administration = self::ADMIN_A): OpenItem
    {
        return new OpenItem($this->itemId($sequence), $this->adminId($administration), new RelationId(new Uuid(sprintf('40000000-0000-4000-8000-%012d', $relation))), $this->entryId($administration, min($sequence, 4)), OpenItemType::Receivable, new Money($amount, new Currency($currency)), $this->date('2026-01-01'), $side);
    }

    private function match(OpenItem $debit, OpenItem $credit, string $amount, string $date = '2026-01-10'): MatchOpenItemsResult
    {
        return $this->app->make(MatchOpenItems::class)->execute($this->adminId(self::ADMIN_A), $debit->id(), $credit->id(), $this->money($amount), $this->date($date), $this->entryId(self::ADMIN_A, 4));
    }

    private function matchAvailable(OpenItem $debit, OpenItem $credit, string $date = '2026-01-10'): MatchOpenItemsResult
    {
        return $this->app->make(MatchOpenItems::class)->executeAvailable($this->adminId(self::ADMIN_A), $debit->id(), $credit->id(), $this->date($date), $this->entryId(self::ADMIN_A, 4));
    }

    /** @return array{OpenItem, OpenItem} */
    private function readPair(OpenItem $debit, OpenItem $credit): array
    {
        $items = $this->app->make(OpenItemReadRepository::class)->findForAdministrationAsOf($this->adminId(self::ADMIN_A), $this->date('2026-12-31'));
        $indexed = [];
        foreach ($items as $item) {
            $indexed[$item->id()->toString()] = $item;
        }

        return [$indexed[$debit->id()->toString()], $indexed[$credit->id()->toString()]];
    }

    private function administration(string $id, string $code): void
    {
        AdministrationRecord::query()->create(['id' => $id, 'code' => $code, 'name' => $code, 'base_currency' => 'EUR', 'status' => 'active']);
    }

    private function relation(string $administration, int $sequence): void
    {
        RelationRecord::query()->create(['id' => sprintf('40000000-0000-4000-8000-%012d', $sequence), 'administration_id' => $administration, 'code' => 'REL-'.$sequence, 'display_name' => 'Relation '.$sequence, 'active' => true]);
    }

    private function postedEntry(string $administration, int $sequence): void
    {
        $journal = sprintf($administration === self::ADMIN_A ? '70000000-0000-4000-8000-%012d' : '71000000-0000-4000-8000-%012d', $sequence);
        JournalRecord::query()->create(['id' => $journal, 'administration_id' => $administration, 'code' => 'J'.$sequence, 'name' => 'Journal '.$sequence, 'type' => 'general', 'status' => 'active']);
        JournalEntryRecord::query()->create(['id' => $this->entryId($administration, $sequence)->toString(), 'administration_id' => $administration, 'journal_id' => $journal, 'posting_date' => '2026-01-01', 'reference' => 'Source '.$sequence, 'status' => JournalEntryStatus::Posted->value]);
    }

    private function adminId(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }

    private function itemId(int $sequence): OpenItemId
    {
        return new OpenItemId(new Uuid(sprintf('30000000-0000-4000-8000-%012d', $sequence)));
    }

    private function settlementId(int $sequence): OpenItemSettlementId
    {
        return new OpenItemSettlementId(new Uuid(sprintf('50000000-0000-4000-8000-%012d', $sequence)));
    }

    private function entryId(string $administration, int $sequence): JournalEntryId
    {
        return new JournalEntryId(new Uuid(sprintf($administration === self::ADMIN_A ? '60000000-0000-4000-8000-%012d' : '61000000-0000-4000-8000-%012d', $sequence)));
    }

    private function date(string $date): PostingDate
    {
        return new PostingDate(new DateTimeImmutable($date));
    }

    private function money(string $amount): Money
    {
        return new Money($amount, new Currency('EUR'));
    }
}
