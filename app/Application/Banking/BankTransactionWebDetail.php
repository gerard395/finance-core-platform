<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Accounting\Entities\OpenItem;
use App\Domain\Banking\Entities\AdministrationBankAccount;
use App\Domain\Banking\Entities\BankTransaction;
use App\Domain\Relations\Entities\Relation;

final readonly class BankTransactionWebDetail
{
    /** @param array<string, OpenItem> $openItems */
    public function __construct(public BankTransaction $transaction, public AdministrationBankAccount $bankAccount, public Relation $relation, public array $openItems, public ?BankTransactionPostingDetail $posting) {}
}
