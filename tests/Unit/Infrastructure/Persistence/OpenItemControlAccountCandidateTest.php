<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Infrastructure\Persistence\OpenItemControlAccountBackfill;
use App\Infrastructure\Persistence\OpenItemControlAccountCandidate;
use Illuminate\Database\SQLiteConnection;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class OpenItemControlAccountCandidateTest extends TestCase
{
    public function test_backfill_adapter_maps_sales_sales_credit_and_purchase_historical_fixtures(): void
    {
        $connection = new SQLiteConnection(new PDO('sqlite::memory:'));
        $connection->statement('CREATE TABLE journal_entries (id TEXT, administration_id TEXT)');
        $connection->statement('CREATE TABLE open_items (id TEXT, administration_id TEXT, journal_entry_id TEXT, open_item_type TEXT, side TEXT, original_amount TEXT, currency TEXT)');
        $connection->statement('CREATE TABLE journal_entry_lines (id TEXT, administration_id TEXT, journal_entry_id TEXT, ledger_account_id TEXT, debit_amount TEXT NULL, credit_amount TEXT NULL, currency TEXT)');
        $connection->statement('CREATE TABLE ledger_accounts (id TEXT, administration_id TEXT, type TEXT)');
        $connection->table('ledger_accounts')->insert([
            ['id' => 'ar', 'administration_id' => 'admin-a', 'type' => 'asset'],
            ['id' => 'ap', 'administration_id' => 'admin-a', 'type' => 'liability'],
        ]);

        foreach ([
            ['sales', 'receivable', 'debit', '121', 'ar'],
            ['sales-credit', 'receivable', 'credit', '40', 'ar'],
            ['purchase', 'payable', 'credit', '109', 'ap'],
        ] as [$id, $type, $side, $amount, $account]) {
            $connection->table('journal_entries')->insert(['id' => 'entry-'.$id, 'administration_id' => 'admin-a']);
            $connection->table('open_items')->insert(['id' => $id, 'administration_id' => 'admin-a', 'journal_entry_id' => 'entry-'.$id, 'open_item_type' => $type, 'side' => $side, 'original_amount' => $amount, 'currency' => 'EUR']);
            $connection->table('journal_entry_lines')->insert(['id' => 'line-'.$id, 'administration_id' => 'admin-a', 'journal_entry_id' => 'entry-'.$id, 'ledger_account_id' => $account, 'debit_amount' => $side === 'debit' ? $amount : null, 'credit_amount' => $side === 'credit' ? $amount : null, 'currency' => 'EUR']);
        }

        self::assertSame([
            'purchase' => 'ap',
            'sales' => 'ar',
            'sales-credit' => 'ar',
        ], (new OpenItemControlAccountBackfill($connection))->mappings());
    }

    #[DataProvider('historicalCases')]
    public function test_selects_the_single_factual_historical_candidate(string $type, string $side, ?string $debit, ?string $credit, string $accountType): void
    {
        $candidate = (new OpenItemControlAccountCandidate)->select(
            $this->openItem($type, $side),
            [$this->line('account-control', $debit, $credit)],
            ['account-control' => ['administration_id' => 'admin-a', 'type' => $accountType]],
        );

        self::assertSame('account-control', $candidate);
    }

    public function test_ambiguous_candidates_are_rejected_without_type_tie_breaking(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('found 2');

        (new OpenItemControlAccountCandidate)->select(
            $this->openItem('receivable', 'debit'),
            [$this->line('asset-a', '100', null), $this->line('liability-b', '100.0', null)],
            [
                'asset-a' => ['administration_id' => 'admin-a', 'type' => 'asset'],
                'liability-b' => ['administration_id' => 'admin-a', 'type' => 'liability'],
            ],
        );
    }

    public function test_missing_candidate_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('found 0');

        (new OpenItemControlAccountCandidate)->select(
            $this->openItem('payable', 'credit'),
            [$this->line('account-ap', '100', null)],
            ['account-ap' => ['administration_id' => 'admin-a', 'type' => 'liability']],
        );
    }

    public function test_cross_tenant_or_wrong_type_candidate_is_rejected_after_factual_selection(): void
    {
        foreach ([
            ['administration_id' => 'admin-b', 'type' => 'asset'],
            ['administration_id' => 'admin-a', 'type' => 'liability'],
        ] as $account) {
            try {
                (new OpenItemControlAccountCandidate)->select(
                    $this->openItem('receivable', 'debit'),
                    [$this->line('account-control', '100', null)],
                    ['account-control' => $account],
                );
                self::fail('Cross-tenant and semantically wrong control accounts must be rejected.');
            } catch (RuntimeException) {
                self::assertTrue(true);
            }
        }
    }

    /** @return iterable<string, array{string, string, ?string, ?string, string}> */
    public static function historicalCases(): iterable
    {
        yield 'sales receivable debit' => ['receivable', 'debit', '100', null, 'asset'];
        yield 'sales credit receivable credit' => ['receivable', 'credit', null, '100', 'asset'];
        yield 'purchase payable credit' => ['payable', 'credit', null, '100', 'liability'];
    }

    /** @return array{id: string, administration_id: string, open_item_type: string, side: string, original_amount: string, currency: string} */
    private function openItem(string $type, string $side): array
    {
        return ['id' => 'open-item', 'administration_id' => 'admin-a', 'open_item_type' => $type, 'side' => $side, 'original_amount' => '100', 'currency' => 'EUR'];
    }

    /** @return array{id: string, administration_id: string, ledger_account_id: string, debit_amount: ?string, credit_amount: ?string, currency: string} */
    private function line(string $account, ?string $debit, ?string $credit): array
    {
        return ['id' => 'line-'.$account, 'administration_id' => 'admin-a', 'ledger_account_id' => $account, 'debit_amount' => $debit, 'credit_amount' => $credit, 'currency' => 'EUR'];
    }
}
