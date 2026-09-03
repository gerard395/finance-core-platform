<?php

declare(strict_types=1);

namespace App\Domain\Banking\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Enums\BankEntryManualAction;
use App\Domain\Banking\ValueObjects\BankEntryReconciliationHistoryId;
use App\Domain\Banking\ValueObjects\BankStatementEntryId;
use App\Domain\Banking\ValueObjects\ReconciliationReason;
use App\Domain\Identity\ValueObjects\UserId;
use DateTimeImmutable;

final readonly class BankEntryReconciliationHistory
{
    public function __construct(
        public BankEntryReconciliationHistoryId $id,
        public AdministrationId $administrationId,
        public BankStatementEntryId $entryId,
        public BankEntryManualAction $action,
        public ?BankEntryReconciliationHistoryId $predecessorId,
        public ReconciliationReason $reason,
        public UserId $actorId,
        public DateTimeImmutable $occurredAt,
        public ?int $sequence = null,
    ) {}
}
