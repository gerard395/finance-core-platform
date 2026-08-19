<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use DateTimeImmutable;
use DomainException;

final readonly class BalanceSheetResult
{
    /**
     * @param  list<BalanceSheetLine>  $assets
     * @param  list<BalanceSheetLine>  $liabilities
     * @param  list<BalanceSheetLine>  $equity
     */
    public function __construct(
        private AdministrationId $administrationId,
        private DateTimeImmutable $balanceDate,
        private Currency $currency,
        private array $assets,
        private array $liabilities,
        private array $equity,
        private Money $totalAssets,
        private Money $totalLiabilities,
        private Money $totalEquity,
    ) {
        $amounts = [$this->totalAssets, $this->totalLiabilities, $this->totalEquity];

        foreach ([$this->assets, $this->liabilities, $this->equity] as $lines) {
            foreach ($lines as $line) {
                $amounts[] = $line->balance();
            }
        }

        foreach ($amounts as $amount) {
            if (! $this->currency->equals($amount->currency())) {
                throw new DomainException('A balance sheet result must use one currency.');
            }
        }
    }

    public function administrationId(): AdministrationId
    {
        return $this->administrationId;
    }

    public function balanceDate(): DateTimeImmutable
    {
        return $this->balanceDate;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    /** @return list<BalanceSheetLine> */
    public function assets(): array
    {
        return $this->assets;
    }

    /** @return list<BalanceSheetLine> */
    public function liabilities(): array
    {
        return $this->liabilities;
    }

    /** @return list<BalanceSheetLine> */
    public function equity(): array
    {
        return $this->equity;
    }

    public function totalAssets(): Money
    {
        return $this->totalAssets;
    }

    public function totalLiabilities(): Money
    {
        return $this->totalLiabilities;
    }

    public function totalEquity(): Money
    {
        return $this->totalEquity;
    }

    public function isBalanced(): bool
    {
        return $this->totalAssets->equals($this->totalLiabilities->add($this->totalEquity));
    }
}
