<?php

declare(strict_types=1);

namespace App\Application\Dashboard;

use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;

final readonly class DashboardOverview
{
    public function __construct(
        private AdministrationId $administrationId,
        private PostingDate $periodStart,
        private PostingDate $periodEnd,
        private Currency $currency,
        private Money $revenue,
        private Money $outstandingReceivables,
        private Money $outstandingPayables,
        private Money $vatPosition,
    ) {}

    public function administrationId(): AdministrationId
    {
        return $this->administrationId;
    }

    public function periodStart(): PostingDate
    {
        return $this->periodStart;
    }

    public function periodEnd(): PostingDate
    {
        return $this->periodEnd;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function revenue(): Money
    {
        return $this->revenue;
    }

    public function outstandingReceivables(): Money
    {
        return $this->outstandingReceivables;
    }

    public function outstandingPayables(): Money
    {
        return $this->outstandingPayables;
    }

    public function vatPosition(): Money
    {
        return $this->vatPosition;
    }
}
