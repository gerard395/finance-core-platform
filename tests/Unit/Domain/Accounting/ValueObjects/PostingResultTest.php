<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Accounting\ValueObjects;

use App\Domain\Accounting\Entities\JournalEntry;
use App\Domain\Accounting\Enums\JournalEntryStatus;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\JournalEntryReference;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Accounting\ValueObjects\PostingResult;
use App\Domain\Accounting\ValueObjects\ValidationError;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PostingResultTest extends TestCase
{
    public function test_a_successful_result_contains_a_journal_entry(): void
    {
        $entry = $this->createEntry();
        $result = PostingResult::success($entry);

        self::assertTrue($result->isSuccess());
        self::assertSame($entry, $result->journalEntry());
        self::assertSame([], $result->validationErrors());
    }

    public function test_a_failed_result_contains_validation_errors(): void
    {
        $error = new ValidationError('minimum_lines', 'At least two lines are required.');
        $errors = [$error];
        $result = PostingResult::failure($errors);
        $errors[] = new ValidationError('unbalanced_entry', 'Entry is not balanced.');

        self::assertFalse($result->isSuccess());
        self::assertNull($result->journalEntry());
        self::assertSame([$error], $result->validationErrors());
    }

    private function createEntry(): JournalEntry
    {
        return new JournalEntry(
            new JournalEntryId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new JournalId(new Uuid('123e4567-e89b-42d3-a456-426614174000')),
            new PostingDate(new DateTimeImmutable('2026-07-16')),
            new JournalEntryReference('Posting result test'),
            JournalEntryStatus::Posted,
        );
    }
}
