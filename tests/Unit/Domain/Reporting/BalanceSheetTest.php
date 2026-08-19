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
use App\Domain\Reporting\BalanceSheet;
use App\Domain\Reporting\BalanceSheetLine;
use App\Domain\Reporting\BalanceSheetResult;
use App\Domain\Reporting\TrialBalanceLine;
use App\Domain\Reporting\TrialBalanceResult;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;

final class BalanceSheetTest extends TestCase
{
    private const string ADMINISTRATION_ID = '10000000-0000-4000-8000-000000000001';

    private const string ASSET_ID = '20000000-0000-4000-8000-000000000001';

    private const string LIABILITY_ID = '20000000-0000-4000-8000-000000000002';

    private const string EQUITY_ID = '20000000-0000-4000-8000-000000000003';

    private const string REVENUE_ID = '20000000-0000-4000-8000-000000000004';

    private const string EXPENSE_ID = '20000000-0000-4000-8000-000000000005';

    public function test_empty_result_has_empty_categories_and_zero_totals(): void
    {
        $result = $this->create([], []);

        self::assertSame([], $result->assets());
        self::assertSame([], $result->liabilities());
        self::assertSame([], $result->equity());
        self::assertTrue($result->totalAssets()->isZero());
        self::assertTrue($result->totalLiabilities()->isZero());
        self::assertTrue($result->totalEquity()->isZero());
        self::assertTrue($result->isBalanced());
    }

    public function test_asset_uses_the_trial_balance_directly(): void
    {
        $asset = $this->account(self::ASSET_ID, LedgerAccountType::Asset);

        $result = $this->create([$asset], [$this->line(self::ASSET_ID, '125.50')]);

        self::assertCount(1, $result->assets());
        $line = $result->assets()[0];
        self::assertSame(self::ASSET_ID, $line->ledgerAccountId()->toString());
        self::assertSame(LedgerAccountType::Asset, $line->ledgerAccountType());
        self::assertSame('125.5', $line->balance()->amount());
        self::assertSame('125.5', $result->totalAssets()->amount());
        self::assertFalse($result->isBalanced());
    }

    public function test_asset_and_liability_form_a_balanced_sheet(): void
    {
        $result = $this->create(
            [
                $this->account(self::ASSET_ID, LedgerAccountType::Asset),
                $this->account(self::LIABILITY_ID, LedgerAccountType::Liability),
            ],
            [
                $this->line(self::ASSET_ID, '100'),
                $this->line(self::LIABILITY_ID, '-100'),
            ],
        );

        self::assertSame('100', $result->totalAssets()->amount());
        self::assertSame('100', $result->totalLiabilities()->amount());
        self::assertSame('0', $result->totalEquity()->amount());
        self::assertTrue($result->isBalanced());
    }

    public function test_asset_liability_and_equity_are_grouped_and_totalled(): void
    {
        $result = $this->create(
            $this->balanceSheetAccounts(),
            [
                $this->line(self::ASSET_ID, '175'),
                $this->line(self::LIABILITY_ID, '-125'),
                $this->line(self::EQUITY_ID, '-50'),
            ],
        );

        self::assertCount(1, $result->assets());
        self::assertCount(1, $result->liabilities());
        self::assertCount(1, $result->equity());
        self::assertSame('175', $result->totalAssets()->amount());
        self::assertSame('125', $result->totalLiabilities()->amount());
        self::assertSame('50', $result->totalEquity()->amount());
        self::assertTrue($result->isBalanced());
    }

    public function test_revenue_is_ignored(): void
    {
        $result = $this->create(
            [$this->account(self::REVENUE_ID, LedgerAccountType::Revenue)],
            [$this->line(self::REVENUE_ID, '-80')],
        );

        $this->assertNoBalanceSheetLines($result);
    }

    public function test_expense_is_ignored(): void
    {
        $result = $this->create(
            [$this->account(self::EXPENSE_ID, LedgerAccountType::Expense)],
            [$this->line(self::EXPENSE_ID, '80')],
        );

        $this->assertNoBalanceSheetLines($result);
    }

    public function test_liability_is_normalized_with_absolute_balance(): void
    {
        $result = $this->create(
            [$this->account(self::LIABILITY_ID, LedgerAccountType::Liability)],
            [$this->line(self::LIABILITY_ID, '-90')],
        );

        self::assertSame('90', $result->liabilities()[0]->balance()->amount());
        self::assertSame('90', $result->totalLiabilities()->amount());
    }

    public function test_equity_is_normalized_with_absolute_balance(): void
    {
        $result = $this->create(
            [$this->account(self::EQUITY_ID, LedgerAccountType::Equity)],
            [$this->line(self::EQUITY_ID, '-45')],
        );

        self::assertSame('45', $result->equity()[0]->balance()->amount());
        self::assertSame('45', $result->totalEquity()->amount());
    }

    public function test_unequal_normalized_totals_are_not_balanced(): void
    {
        $result = $this->create(
            [
                $this->account(self::ASSET_ID, LedgerAccountType::Asset),
                $this->account(self::LIABILITY_ID, LedgerAccountType::Liability),
            ],
            [
                $this->line(self::ASSET_ID, '100'),
                $this->line(self::LIABILITY_ID, '-99.99'),
            ],
        );

        self::assertFalse($result->isBalanced());
    }

    public function test_wrong_balance_date_is_rejected(): void
    {
        $trialBalance = $this->trialBalance([]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Balance sheet date must equal the trial balance end date.');

        (new BalanceSheet)->create($trialBalance, [], new DateTimeImmutable('2026-08-01'));
    }

    public function test_administration_currency_and_balance_date_are_preserved(): void
    {
        $administrationId = $this->administrationId();
        $currency = new Currency('USD');
        $balanceDate = new DateTimeImmutable('2026-07-31');
        $trialBalance = $this->trialBalance([], $administrationId, $currency, $balanceDate);

        $result = (new BalanceSheet)->create($trialBalance, [], new DateTimeImmutable('2026-07-31'));

        self::assertSame($administrationId, $result->administrationId());
        self::assertSame($currency, $result->currency());
        self::assertSame($balanceDate, $result->balanceDate());
        self::assertSame($currency, $result->totalAssets()->currency());
        self::assertSame($currency, $result->totalLiabilities()->currency());
        self::assertSame($currency, $result->totalEquity()->currency());
    }

    public function test_input_objects_are_not_mutated(): void
    {
        $account = $this->account(self::LIABILITY_ID, LedgerAccountType::Liability);
        $trialBalanceLine = $this->line(self::LIABILITY_ID, '-70');
        $trialBalance = $this->trialBalance([$trialBalanceLine]);
        $accountName = $account->name();
        $accountStatus = $account->status();
        $sourceBalance = $trialBalanceLine->balance();
        $sourceLines = $trialBalance->lines();

        (new BalanceSheet)->create($trialBalance, [$account], $trialBalance->endDate());

        self::assertSame($accountName, $account->name());
        self::assertSame($accountStatus, $account->status());
        self::assertSame($sourceBalance, $trialBalanceLine->balance());
        self::assertSame('-70', $trialBalanceLine->balance()->amount());
        self::assertSame($sourceLines, $trialBalance->lines());
    }

    public function test_all_amounts_remain_decimal_strings_without_floats(): void
    {
        $result = $this->create(
            $this->balanceSheetAccounts(),
            [
                $this->line(self::ASSET_ID, '0.12345678'),
                $this->line(self::LIABILITY_ID, '-0.1'),
                $this->line(self::EQUITY_ID, '-0.02345678'),
            ],
        );

        foreach ([$result->assets(), $result->liabilities(), $result->equity()] as $lines) {
            foreach ($lines as $line) {
                self::assertIsString($line->balance()->amount());
            }
        }
        self::assertIsString($result->totalAssets()->amount());
        self::assertIsString($result->totalLiabilities()->amount());
        self::assertIsString($result->totalEquity()->amount());
    }

    /**
     * @param  list<LedgerAccount>  $accounts
     * @param  list<TrialBalanceLine>  $lines
     */
    private function create(array $accounts, array $lines): BalanceSheetResult
    {
        $trialBalance = $this->trialBalance($lines);

        return (new BalanceSheet)->create($trialBalance, $accounts, $trialBalance->endDate());
    }

    /** @return list<LedgerAccount> */
    private function balanceSheetAccounts(): array
    {
        return [
            $this->account(self::ASSET_ID, LedgerAccountType::Asset),
            $this->account(self::LIABILITY_ID, LedgerAccountType::Liability),
            $this->account(self::EQUITY_ID, LedgerAccountType::Equity),
        ];
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
        ?DateTimeImmutable $endDate = null,
    ): TrialBalanceResult {
        $currency ??= new Currency('EUR');

        return new TrialBalanceResult(
            $lines,
            Money::zero($currency),
            Money::zero($currency),
            $administrationId ?? $this->administrationId(),
            new DateTimeImmutable('2026-01-01'),
            $endDate ?? new DateTimeImmutable('2026-07-31'),
            $currency,
        );
    }

    /** @param list<BalanceSheetLine> $lines */
    private function assertNoLines(array $lines): void
    {
        self::assertSame([], $lines);
    }

    private function assertNoBalanceSheetLines(BalanceSheetResult $result): void
    {
        $this->assertNoLines($result->assets());
        $this->assertNoLines($result->liabilities());
        $this->assertNoLines($result->equity());
        self::assertTrue($result->totalAssets()->isZero());
        self::assertTrue($result->totalLiabilities()->isZero());
        self::assertTrue($result->totalEquity()->isZero());
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
