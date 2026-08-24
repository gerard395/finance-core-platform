<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Domain\Accounting\Entities\Journal;
use App\Domain\Administration\ValueObjects\AdministrationId;

interface JournalStore
{
    public function save(AdministrationId $administrationId, Journal $journal): void;
}
