<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Accounting\Entities;

use App\Domain\Accounting\Entities\JournalEntry;
use App\Domain\Accounting\Enums\JournalEntryStatus;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\JournalEntryReference;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class JournalEntryTest extends TestCase
{
    public function test_it_is_constructed_with_all_values_exposed_and_starts_as_draft(): void
    {
        $id = new JournalEntryId(new Uuid('550e8400-e29b-41d4-a716-446655440000'));
        $journalId = new JournalId(new Uuid('123e4567-e89b-42d3-a456-426614174000'));
        $postingDate = new PostingDate(new DateTimeImmutable('2026-07-16'));
        $reference = new JournalEntryReference('Opening entry');
        $entry = new JournalEntry($id, $journalId, $postingDate, $reference, JournalEntryStatus::Draft);

        self::assertSame($id, $entry->id());
        self::assertSame($journalId, $entry->journalId());
        self::assertSame($postingDate, $entry->postingDate());
        self::assertSame($reference, $entry->reference());
        self::assertSame(JournalEntryStatus::Draft, $entry->status());
        self::assertTrue($entry->isDraft());
        self::assertFalse($entry->isPosted());
    }

    public function test_it_transitions_from_draft_to_posted_without_changing_immutable_values(): void
    {
        $entry = $this->createEntry();
        $id = $entry->id();
        $journalId = $entry->journalId();
        $postingDate = $entry->postingDate();
        $reference = $entry->reference();

        $entry->post();

        self::assertSame(JournalEntryStatus::Posted, $entry->status());
        self::assertFalse($entry->isDraft());
        self::assertTrue($entry->isPosted());
        self::assertSame($id, $entry->id());
        self::assertSame($journalId, $entry->journalId());
        self::assertSame($postingDate, $entry->postingDate());
        self::assertSame($reference, $entry->reference());
    }

    public function test_post_is_idempotent_and_posted_cannot_return_to_draft(): void
    {
        $entry = $this->createEntry();

        $entry->post();
        $entry->post();

        self::assertSame(JournalEntryStatus::Posted, $entry->status());
        self::assertFalse(method_exists(JournalEntry::class, 'draft'));
        self::assertFalse(method_exists(JournalEntry::class, 'unpost'));
    }

    public function test_it_has_no_lines_or_balance_api(): void
    {
        self::assertFalse(method_exists(JournalEntry::class, 'lines'));
        self::assertFalse(method_exists(JournalEntry::class, 'addLine'));
        self::assertFalse(method_exists(JournalEntry::class, 'balance'));
        self::assertFalse(method_exists(JournalEntry::class, 'isBalanced'));
    }

    private function createEntry(): JournalEntry
    {
        return new JournalEntry(
            new JournalEntryId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new JournalId(new Uuid('123e4567-e89b-42d3-a456-426614174000')),
            new PostingDate(new DateTimeImmutable('2026-07-16')),
            new JournalEntryReference('Opening entry'),
            JournalEntryStatus::Draft,
        );
    }
}
