<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Entities\OpenItem;
use App\Domain\Accounting\Entities\OpenItemMatch;
use App\Domain\Accounting\Enums\OpenItemSide;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\OpenItemMatchId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Shared\Finance\Money;
use DomainException;

final readonly class OpenItemMatchingPolicy
{
    public function create(
        OpenItemMatchId $id,
        OpenItem $debit,
        OpenItem $credit,
        Money $amount,
        PostingDate $occurredOn,
        JournalEntryId $sourceJournalEntryId,
    ): OpenItemMatch {
        if ($debit->side() !== OpenItemSide::Debit || $credit->side() !== OpenItemSide::Credit) {
            throw new DomainException('Open item matching requires Debit and Credit sides.');
        }
        if ($debit->type() !== $credit->type()) {
            throw new DomainException('Open item matching requires the same subledger type.');
        }
        if (! $debit->administrationId()->equals($credit->administrationId())) {
            throw new DomainException('Open item matching requires the same Administration.');
        }
        if (! $debit->relationId()->equals($credit->relationId())) {
            throw new DomainException('Open item matching requires the same Relation.');
        }
        if (! $debit->originalAmount()->currency()->equals($credit->originalAmount()->currency())
            || ! $amount->currency()->equals($debit->originalAmount()->currency())) {
            throw new DomainException('Open item matching requires the same Currency.');
        }
        if ($occurredOn->value() < $debit->openedOn()->value() || $occurredOn->value() < $credit->openedOn()->value()) {
            throw new DomainException('Open item match date cannot precede either opening date.');
        }
        if (! $amount->isPositive()
            || $amount->subtract($debit->openAmountAt($occurredOn))->isPositive()
            || $amount->subtract($credit->openAmountAt($occurredOn))->isPositive()) {
            throw new DomainException('Open item match amount cannot exceed either open amount.');
        }

        return new OpenItemMatch($id, $debit->administrationId(), $debit->id(), $credit->id(), $amount, $occurredOn, $sourceJournalEntryId);
    }
}
