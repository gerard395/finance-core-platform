<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Domain\Accounting\ValueObjects\OpenItemMatchId;

interface OpenItemMatchIdentityGenerator
{
    public function next(): OpenItemMatchId;
}
