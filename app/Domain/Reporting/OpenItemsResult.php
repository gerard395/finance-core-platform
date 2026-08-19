<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use App\Domain\Accounting\Enums\OpenItemStatus;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use DomainException;

final readonly class OpenItemsResult
{
    /** @param list<OpenItemsLine> $lines */
    public function __construct(
        private AdministrationId $administrationId,
        private PostingDate $asOfDate,
        private Currency $currency,
        private array $lines,
        private Money $totalOriginalAmount,
        private Money $totalOpenAmount,
    ) {
        foreach ([$this->totalOriginalAmount, $this->totalOpenAmount] as $amount) {
            $this->assertCurrency($amount);
        }

        foreach ($this->lines as $line) {
            $this->assertCurrency($line->originalAmount());
            $this->assertCurrency($line->openAmount());
        }
    }

    public function administrationId(): AdministrationId
    {
        return $this->administrationId;
    }

    public function asOfDate(): PostingDate
    {
        return $this->asOfDate;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    /** @return list<OpenItemsLine> */
    public function lines(): array
    {
        return $this->lines;
    }

    public function totalOriginalAmount(): Money
    {
        return $this->totalOriginalAmount;
    }

    public function totalOpenAmount(): Money
    {
        return $this->totalOpenAmount;
    }

    public function countOpen(): int
    {
        return $this->countStatus(OpenItemStatus::Open);
    }

    public function countPartiallySettled(): int
    {
        return $this->countStatus(OpenItemStatus::PartiallySettled);
    }

    public function countClosed(): int
    {
        return $this->countStatus(OpenItemStatus::Closed);
    }

    private function countStatus(OpenItemStatus $status): int
    {
        return count(array_filter(
            $this->lines,
            static fn (OpenItemsLine $line): bool => $line->status() === $status,
        ));
    }

    private function assertCurrency(Money $amount): void
    {
        if (! $this->currency->equals($amount->currency())) {
            throw new DomainException('An open items result must use one currency.');
        }
    }
}
