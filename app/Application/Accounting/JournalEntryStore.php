<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Domain\Accounting\Entities\JournalEntry;

interface JournalEntryStore
{
    public function append(JournalEntry $journalEntry): void;
}
