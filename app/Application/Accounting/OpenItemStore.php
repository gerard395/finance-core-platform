<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Domain\Accounting\Entities\OpenItem;

interface OpenItemStore
{
    public function append(OpenItem $openItem): void;
}
