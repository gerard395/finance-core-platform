<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\BankTransactionSettlementReversalLink;
use App\Domain\Banking\ValueObjects\BankTransactionReversalId;

interface BankTransactionSettlementReversalLinkRepository
{
    /** @return list<BankTransactionSettlementReversalLink> */
    public function findByReversal(AdministrationId $administrationId, BankTransactionReversalId $reversalId, bool $forUpdate = false): array;

    public function appendLink(BankTransactionSettlementReversalLink $link): bool;
}
