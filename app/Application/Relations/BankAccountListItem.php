<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Domain\Relations\Enums\BankAccountStatus;
use App\Domain\Relations\ValueObjects\AccountName;
use App\Domain\Relations\ValueObjects\BankAccountId;
use App\Domain\Relations\ValueObjects\Bic;
use App\Domain\Relations\ValueObjects\Iban;

readonly class BankAccountListItem
{
    public function __construct(
        public BankAccountId $id,
        public Iban $iban,
        public ?Bic $bic,
        public AccountName $accountName,
        public BankAccountStatus $status,
    ) {}
}
