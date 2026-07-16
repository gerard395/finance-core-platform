<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Entities\JournalEntry;
use App\Domain\Accounting\Enums\JournalEntryStatus;
use App\Domain\Accounting\Requests\PostingRequest;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\PostingResult;
use Closure;

final readonly class PostingEngine
{
    /** @param Closure(): JournalEntryId $journalEntryIdFactory */
    public function __construct(
        private PostingValidation $validation,
        private Closure $journalEntryIdFactory,
    ) {}

    public function post(PostingRequest $request): PostingResult
    {
        $validationResult = $this->validation->validate($request);

        if (! $validationResult->isValid()) {
            return PostingResult::failure($validationResult->errors());
        }

        $journalEntry = new JournalEntry(
            ($this->journalEntryIdFactory)(),
            $request->journalId(),
            $request->postingDate(),
            $request->reference(),
            JournalEntryStatus::Draft,
        );

        foreach ($request->lines() as $line) {
            $journalEntry->addLine($line);
        }

        $journalEntry->post();

        return PostingResult::success($journalEntry);
    }
}
