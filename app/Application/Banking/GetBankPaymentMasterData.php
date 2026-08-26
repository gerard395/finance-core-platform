<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Application\Accounting\OpenItemReadRepository;
use App\Application\Relations\RelationReadRepository;
use App\Domain\Accounting\Enums\OpenItemSide;
use App\Domain\Accounting\Enums\OpenItemType;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\Entities\Relation;
use DateTimeImmutable;

final readonly class GetBankPaymentMasterData
{
    public function __construct(private AdministrationBankAccountRepository $banks, private RelationReadRepository $relations, private OpenItemReadRepository $openItems) {}

    public function execute(AdministrationId $administrationId): BankPaymentMasterData
    {
        $items = array_values(array_filter(
            $this->openItems->findForAdministrationAsOf($administrationId, new PostingDate(new DateTimeImmutable('today'))),
            static fn ($item): bool => $item->originalAmount()->currency()->code() === 'EUR'
                && $item->openAmount()->isPositive()
                && (($item->type() === OpenItemType::Receivable && $item->side() === OpenItemSide::Debit)
                    || ($item->type() === OpenItemType::Payable && $item->side() === OpenItemSide::Credit)),
        ));
        $relationIds = array_fill_keys(array_map(static fn ($item): string => $item->relationId()->toString(), $items), true);
        $relations = array_values(array_filter(
            $this->relations->findForAdministration($administrationId),
            static fn (Relation $relation): bool => isset($relationIds[$relation->id()->toString()]),
        ));

        return new BankPaymentMasterData(
            array_values(array_filter($this->banks->findForAdministration($administrationId), static fn ($bank): bool => $bank->isActive() && $bank->currency()->code() === 'EUR')),
            $relations,
            $items,
        );
    }
}
