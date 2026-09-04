<?php

declare(strict_types=1);

namespace App\Domain\Banking\Entities;

use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Enums\BankEntryReconciliationIntent;
use App\Domain\Banking\ValueObjects\BankEntryReconciliationId;
use App\Domain\Banking\ValueObjects\BankStatementEntryId;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Identity\ValueObjects\UserId;
use DateTimeImmutable;

final readonly class BankEntryReconciliation
{
    public function __construct(
        public BankEntryReconciliationId $id,
        public AdministrationId $administrationId,
        public BankStatementEntryId $entryId,
        public BankTransactionId $bankTransactionId,
        public BankEntryReconciliationIntent $intent,
        public DateTimeImmutable $bookingDate,
        public PostingDate $postingDate,
        public UserId $actorId,
        public DateTimeImmutable $occurredAt,
        public ?BankEntryReconciliationId $replacesId = null,
    ) {}
}
