<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Shared\Finance\Money;

final readonly class ReversedOpenItemBalance
{
    public function __construct(public OpenItemId $openItemId, public Money $openAmount) {}
}
