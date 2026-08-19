<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Requests;

use App\Domain\Accounting\Entities\JournalEntryLine;
use App\Domain\Accounting\ValueObjects\JournalEntryReference;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;

final readonly class PostingRequest
{
    /** @var list<JournalEntryLine> */
    private array $lines;

    /** @param list<JournalEntryLine> $lines */
    public function __construct(
        private AdministrationId $administrationId,
        private JournalId $journalId,
        private PostingDate $postingDate,
        private JournalEntryReference $reference,
        array $lines,
    ) {
        $this->lines = array_values($lines);
    }

    public function administrationId(): AdministrationId
    {
        return $this->administrationId;
    }

    public function journalId(): JournalId
    {
        return $this->journalId;
    }

    public function postingDate(): PostingDate
    {
        return $this->postingDate;
    }

    public function reference(): JournalEntryReference
    {
        return $this->reference;
    }

    /** @return list<JournalEntryLine> */
    public function lines(): array
    {
        return $this->lines;
    }
}
