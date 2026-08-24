<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Domain\Accounting\Entities\OpenItem;

final readonly class OpenItemMatchPair
{
    public function __construct(public OpenItem $debit, public OpenItem $credit) {}
}
