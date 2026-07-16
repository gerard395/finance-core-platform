<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Accounting\Entities;

use App\Domain\Accounting\Entities\JournalEntry;
use App\Domain\Accounting\Entities\JournalEntryLine;
use App\Domain\Accounting\Enums\JournalEntryStatus;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\JournalEntryReference;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use DomainException;
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

    public function test_it_owns_lines_and_exposes_them_by_identity(): void
    {
        $entry = $this->createEntry();
        $line = $this->createLine();

        $entry->addLine($line);

        self::assertSame([$line], $entry->lines());
        self::assertSame($line, $entry->line($line->id()));
        self::assertTrue($entry->hasLine($line->id()));
        self::assertNull($entry->line($this->unknownLineId()));
        self::assertFalse($entry->hasLine($this->unknownLineId()));
    }

    public function test_it_rejects_a_duplicate_line_identity(): void
    {
        $entry = $this->createEntry();
        $entry->addLine($this->createLine());

        $this->expectException(DomainException::class);

        $entry->addLine($this->createLine());
    }

    public function test_removing_a_line_is_idempotent(): void
    {
        $entry = $this->createEntry();
        $line = $this->createLine();
        $entry->addLine($line);

        $entry->removeLine($line->id());
        $entry->removeLine($line->id());
        $entry->removeLine($this->unknownLineId());

        self::assertSame([], $entry->lines());
        self::assertFalse($entry->hasLine($line->id()));
    }

    public function test_posted_entry_lines_cannot_be_changed(): void
    {
        $entry = $this->createEntry();
        $entry->post();

        try {
            $entry->addLine($this->createLine());
            self::fail('Expected adding a line to a posted entry to fail.');
        } catch (DomainException) {
            self::assertSame([], $entry->lines());
        }

        $this->expectException(DomainException::class);
        $entry->removeLine($this->unknownLineId());
    }

    public function test_it_still_has_no_balance_api(): void
    {
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

    private function createLine(): JournalEntryLine
    {
        return new JournalEntryLine(
            new JournalEntryLineId(new Uuid('936da01f-9abd-4d9d-80c7-02af85c822a8')),
            new LedgerAccountId(new Uuid('6ba7b810-9dad-41d1-80b4-00c04fd430c8')),
            new Money('100', new Currency('EUR')),
            null,
            'Opening balance',
        );
    }

    private function unknownLineId(): JournalEntryLineId
    {
        return new JournalEntryLineId(new Uuid('6ba7b811-9dad-41d1-80b4-00c04fd430c8'));
    }
}
