<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Accounting\Entities\OpenItem;
use App\Domain\Banking\Entities\AdministrationBankAccount;
use App\Domain\Relations\Entities\Relation;

final readonly class BankPaymentMasterData
{
    /** @param list<AdministrationBankAccount> $bankAccounts @param list<Relation> $relations @param list<OpenItem> $openItems */
    public function __construct(public array $bankAccounts, public array $relations, public array $openItems) {}
}
