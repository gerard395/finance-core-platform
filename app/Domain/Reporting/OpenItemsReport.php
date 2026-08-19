<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use App\Domain\Accounting\Entities\OpenItem;
use App\Domain\Accounting\Enums\OpenItemStatus;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use DomainException;

final readonly class OpenItemsReport
{
    /** @param list<OpenItem> $openItems */
    public function generate(
        array $openItems,
        AdministrationId $administrationId,
        Currency $currency,
        PostingDate $asOfDate,
        ?RelationId $relationId = null,
        bool $includeClosed = false,
    ): OpenItemsResult {
        $lines = [];

        foreach ($openItems as $openItem) {
            if (! $openItem->administrationId()->equals($administrationId)
                || $openItem->openedOn()->value() > $asOfDate->value()
                || ($relationId !== null && ! $openItem->relationId()->equals($relationId))) {
                continue;
            }

            if (! $openItem->originalAmount()->currency()->equals($currency)) {
                throw new DomainException('Open item currency must match the report currency.');
            }

            $openAmount = $openItem->openAmountAt($asOfDate);
            $status = $openItem->statusAt($asOfDate);

            if (! $includeClosed && $status === OpenItemStatus::Closed) {
                continue;
            }

            $lines[] = new OpenItemsLine(
                $openItem->id(),
                $openItem->relationId(),
                $openItem->journalEntryId(),
                $openItem->openedOn(),
                $openItem->originalAmount(),
                $openAmount,
                $status,
            );
        }

        usort($lines, static function (OpenItemsLine $left, OpenItemsLine $right): int {
            $dateOrder = $left->openedOn()->value() <=> $right->openedOn()->value();

            return $dateOrder !== 0
                ? $dateOrder
                : strcmp($left->openItemId()->toString(), $right->openItemId()->toString());
        });

        $totalOriginalAmount = Money::zero($currency);
        $totalOpenAmount = Money::zero($currency);

        foreach ($lines as $line) {
            $totalOriginalAmount = $totalOriginalAmount->add($line->originalAmount());
            $totalOpenAmount = $totalOpenAmount->add($line->openAmount());
        }

        return new OpenItemsResult(
            $administrationId,
            $asOfDate,
            $currency,
            $lines,
            $totalOriginalAmount,
            $totalOpenAmount,
        );
    }
}
