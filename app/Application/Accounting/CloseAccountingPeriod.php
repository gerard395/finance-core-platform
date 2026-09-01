<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Application\Shared\TransactionManager;
use App\Domain\Accounting\ValueObjects\AccountingPeriodId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Identity\ValueObjects\UserId;
use DateTimeImmutable;

final readonly class CloseAccountingPeriod
{
    public function __construct(private TransactionManager $transactions, private BookYearRepository $years) {}

    public function execute(AdministrationId $a, AccountingPeriodId $id, string $reason, UserId $actor, DateTimeImmutable $at): AccountingPeriodMutationStatus
    {
        return $this->transactions->run(fn () => AccountingPeriodMutationStatus::from($this->years->transition($a, $id, $reason, $actor, $at)->value));
    }
}
