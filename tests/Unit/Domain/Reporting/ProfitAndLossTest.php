<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Reporting;

use App\Domain\Accounting\Entities\LedgerAccount;
use App\Domain\Accounting\Enums\LedgerAccountStatus;
use App\Domain\Accounting\Enums\LedgerAccountType;
use App\Domain\Accounting\ValueObjects\LedgerAccountCode;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\LedgerAccountName;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Reporting\ProfitAndLoss;
use App\Domain\Reporting\ProfitAndLossResult;
use App\Domain\Reporting\TrialBalanceLine;
use App\Domain\Reporting\TrialBalanceResult;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ProfitAndLossTest extends TestCase
{
    private const string ADMINISTRATION_ID = '10000000-0000-4000-8000-000000000001';

    private const string REVENUE_ID = '20000000-0000-4000-8000-000000000001';

    private const string EXPENSE_ID = '20000000-0000-4000-8000-000000000002';

    private const string ASSET_ID = '20000000-0000-4000-8000-000000000003';

    private const string LIABILITY_ID = '20000000-0000-4000-8000-000000000004';

    private const string EQUITY_ID = '20000000-0000-4000-8000-000000000005';

    public function test_empty_result_has_empty_categories_and_zero_amounts(): void
    {
        $result = $this->create([], []);

        self::assertSame([], $result->revenue());
        self::assertSame([], $result->expenses());
        self::assertTrue($result->totalRevenue()->isZero());
        self::assertTrue($result->totalExpenses()->isZero());
        self::assertTrue($result->netResult()->isZero());
        self::assertSame('0', $result->netResult()->amount());
    }

    public function test_revenue_is_normalized_and_totalled(): void
    {
        $result = $this->create(
            [$this->account(self::REVENUE_ID, LedgerAccountType::Revenue)],
            [$this->line(self::REVENUE_ID, '-125.50')],
        );

        self::assertCount(1, $result->revenue());
        $line = $result->revenue()[0];
        self::assertSame(self::REVENUE_ID, $line->ledgerAccountId()->toString());
        self::assertSame(LedgerAccountType::Revenue, $line->ledgerAccountType());
        self::assertSame('125.5', $line->amount()->amount());
        self::assertSame('125.5', $result->totalRevenue()->amount());
        self::assertSame('0', $result->totalExpenses()->amount());
        self::assertSame('125.5', $result->netResult()->amount());
    }

    public function test_expense_is_used_directly_and_totalled(): void
    {
        $result = $this->create(
            [$this->account(self::EXPENSE_ID, LedgerAccountType::Expense)],
            [$this->line(self::EXPENSE_ID, '45.25')],
        );

        self::assertCount(1, $result->expenses());
        $line = $result->expenses()[0];
        self::assertSame(self::EXPENSE_ID, $line->ledgerAccountId()->toString());
        self::assertSame(LedgerAccountType::Expense, $line->ledgerAccountType());
        self::assertSame('45.25', $line->amount()->amount());
        self::assertSame('0', $result->totalRevenue()->amount());
        self::assertSame('45.25', $result->totalExpenses()->amount());
        self::assertSame('-45.25', $result->netResult()->amount());
    }

    public function test_revenue_and_expenses_are_accumulated_exactly(): void
    {
        $result = $this->create(
            [
                $this->account(self::REVENUE_ID, LedgerAccountType::Revenue),
                $this->account(self::EXPENSE_ID, LedgerAccountType::Expense),
            ],
            [
                $this->line(self::REVENUE_ID, '-100.12345678'),
                $this->line(self::EXPENSE_ID, '40.02345678'),
            ],
        );

        self::assertSame('100.12345678', $result->totalRevenue()->amount());
        self::assertSame('40.02345678', $result->totalExpenses()->amount());
        self::assertSame('60.1', $result->netResult()->amount());
    }

    public function test_asset_is_ignored(): void
    {
        $result = $this->ignoredResult(self::ASSET_ID, LedgerAccountType::Asset, '80');

        $this->assertNoProfitAndLossLines($result);
    }

    public function test_liability_is_ignored(): void
    {
        $result = $this->ignoredResult(self::LIABILITY_ID, LedgerAccountType::Liability, '-80');

        $this->assertNoProfitAndLossLines($result);
    }

    public function test_equity_is_ignored(): void
    {
        $result = $this->ignoredResult(self::EQUITY_ID, LedgerAccountType::Equity, '-80');

        $this->assertNoProfitAndLossLines($result);
    }

    public function test_profit_has_a_positive_net_result(): void
    {
        $result = $this->resultForRevenueAndExpense('100', '75');

        self::assertSame('25', $result->netResult()->amount());
        self::assertTrue($result->netResult()->isPositive());
    }

    public function test_loss_has_a_negative_net_result(): void
    {
        $result = $this->resultForRevenueAndExpense('75', '100');

        self::assertSame('-25', $result->netResult()->amount());
        self::assertTrue($result->netResult()->isNegative());
    }

    public function test_break_even_has_canonical_zero_net_result(): void
    {
        $result = $this->resultForRevenueAndExpense('75', '75');

        self::assertSame('0', $result->netResult()->amount());
        self::assertTrue($result->netResult()->isZero());
        self::assertFalse($result->netResult()->isPositive());
        self::assertFalse($result->netResult()->isNegative());
    }

    public function test_reporting_context_is_preserved(): void
    {
        $administrationId = $this->administrationId();
        $startDate = new DateTimeImmutable('2026-01-01');
        $endDate = new DateTimeImmutable('2026-07-31');
        $currency = new Currency('USD');
        $trialBalance = $this->trialBalance([], $administrationId, $currency, $startDate, $endDate);

        $result = (new ProfitAndLoss)->create($trialBalance, []);

        self::assertSame($administrationId, $result->administrationId());
        self::assertSame($startDate, $result->startDate());
        self::assertSame($endDate, $result->endDate());
        self::assertSame($currency, $result->currency());
        self::assertSame($currency, $result->totalRevenue()->currency());
        self::assertSame($currency, $result->totalExpenses()->currency());
        self::assertSame($currency, $result->netResult()->currency());
    }

    public function test_input_objects_are_not_mutated(): void
    {
        $account = $this->account(self::REVENUE_ID, LedgerAccountType::Revenue);
        $trialBalanceLine = $this->line(self::REVENUE_ID, '-70');
        $trialBalance = $this->trialBalance([$trialBalanceLine]);
        $accountName = $account->name();
        $accountStatus = $account->status();
        $sourceBalance = $trialBalanceLine->balance();
        $sourceLines = $trialBalance->lines();

        (new ProfitAndLoss)->create($trialBalance, [$account]);

        self::assertSame($accountName, $account->name());
        self::assertSame($accountStatus, $account->status());
        self::assertSame($sourceBalance, $trialBalanceLine->balance());
        self::assertSame('-70', $trialBalanceLine->balance()->amount());
        self::assertSame($sourceLines, $trialBalance->lines());
    }

    public function test_all_amounts_remain_decimal_strings_without_floats(): void
    {
        $result = $this->resultForRevenueAndExpense('0.12345678', '0.02345678');

        foreach ([$result->revenue(), $result->expenses()] as $lines) {
            foreach ($lines as $line) {
                self::assertIsString($line->amount()->amount());
            }
        }
        self::assertIsString($result->totalRevenue()->amount());
        self::assertIsString($result->totalExpenses()->amount());
        self::assertIsString($result->netResult()->amount());
        self::assertSame('0.1', $result->netResult()->amount());
    }

    /**
     * @param  list<LedgerAccount>  $accounts
     * @param  list<TrialBalanceLine>  $lines
     */
    private function create(array $accounts, array $lines): ProfitAndLossResult
    {
        return (new ProfitAndLoss)->create($this->trialBalance($lines), $accounts);
    }

    private function ignoredResult(string $id, LedgerAccountType $type, string $balance): ProfitAndLossResult
    {
        return $this->create([$this->account($id, $type)], [$this->line($id, $balance)]);
    }

    private function resultForRevenueAndExpense(string $revenue, string $expense): ProfitAndLossResult
    {
        return $this->create(
            [
                $this->account(self::REVENUE_ID, LedgerAccountType::Revenue),
                $this->account(self::EXPENSE_ID, LedgerAccountType::Expense),
            ],
            [
                $this->line(self::REVENUE_ID, '-'.$revenue),
                $this->line(self::EXPENSE_ID, $expense),
            ],
        );
    }

    private function account(string $id, LedgerAccountType $type): LedgerAccount
    {
        return new LedgerAccount(
            $this->ledgerAccountId($id),
            new LedgerAccountCode(substr($id, -4)),
            new LedgerAccountName($type->value.' account'),
            $type,
            LedgerAccountStatus::Active,
        );
    }

    private function line(string $accountId, string $balance, ?Currency $currency = null): TrialBalanceLine
    {
        $currency ??= new Currency('EUR');
        $isCredit = str_starts_with($balance, '-');
        $amount = new Money($isCredit ? substr($balance, 1) : $balance, $currency);

        return new TrialBalanceLine(
            $this->ledgerAccountId($accountId),
            $isCredit ? Money::zero($currency) : $amount,
            $isCredit ? $amount : Money::zero($currency),
            new Money($balance, $currency),
        );
    }

    /** @param list<TrialBalanceLine> $lines */
    private function trialBalance(
        array $lines,
        ?AdministrationId $administrationId = null,
        ?Currency $currency = null,
        ?DateTimeImmutable $startDate = null,
        ?DateTimeImmutable $endDate = null,
    ): TrialBalanceResult {
        $currency ??= new Currency('EUR');

        return new TrialBalanceResult(
            $lines,
            Money::zero($currency),
            Money::zero($currency),
            $administrationId ?? $this->administrationId(),
            $startDate ?? new DateTimeImmutable('2026-01-01'),
            $endDate ?? new DateTimeImmutable('2026-07-31'),
            $currency,
        );
    }

    private function assertNoProfitAndLossLines(ProfitAndLossResult $result): void
    {
        self::assertSame([], $result->revenue());
        self::assertSame([], $result->expenses());
        self::assertTrue($result->totalRevenue()->isZero());
        self::assertTrue($result->totalExpenses()->isZero());
        self::assertTrue($result->netResult()->isZero());
    }

    private function administrationId(): AdministrationId
    {
        return new AdministrationId(new Uuid(self::ADMINISTRATION_ID));
    }

    private function ledgerAccountId(string $id): LedgerAccountId
    {
        return new LedgerAccountId(new Uuid($id));
    }
}
