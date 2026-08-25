<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\ValueObjects;

use App\Domain\Accounting\Enums\LedgerAccountType;
use App\Domain\Accounting\ValueObjects\LedgerAccountCode;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\LedgerAccountName;
use InvalidArgumentException;

final readonly class PurchaseAccountSnapshot
{
    public function __construct(public LedgerAccountId $id, public LedgerAccountCode $code, public LedgerAccountName $name, public LedgerAccountType $type)
    {
        if (! in_array($type, [LedgerAccountType::Expense, LedgerAccountType::Asset], true)) {
            throw new InvalidArgumentException('Purchase line account must be Expense or Asset.');
        }
    }
}
