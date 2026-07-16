<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Accounting\Entities;

use App\Domain\Accounting\Entities\Journal;
use App\Domain\Accounting\Enums\JournalStatus;
use App\Domain\Accounting\Enums\JournalType;
use App\Domain\Accounting\ValueObjects\JournalCode;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\JournalName;
use App\Domain\Shared\Identity\Uuid;
use PHPUnit\Framework\TestCase;

final class JournalTest extends TestCase
{
    public function test_it_is_constructed_with_all_values_exposed(): void
    {
        $id = new JournalId(new Uuid('550e8400-e29b-41d4-a716-446655440000'));
        $code = new JournalCode('sales01');
        $name = new JournalName('Sales journal');
        $journal = new Journal($id, $code, $name, JournalType::Sales, JournalStatus::Active);

        self::assertSame($id, $journal->id());
        self::assertSame($code, $journal->code());
        self::assertSame($name, $journal->name());
        self::assertSame(JournalType::Sales, $journal->type());
        self::assertSame(JournalStatus::Active, $journal->status());
        self::assertTrue($journal->isActive());
    }

    public function test_it_can_be_renamed_without_changing_identity_code_or_type(): void
    {
        $journal = $this->createJournal();
        $id = $journal->id();
        $code = $journal->code();
        $type = $journal->type();

        $journal->rename(new JournalName('Domestic sales'));

        self::assertSame('Domestic sales', $journal->name()->value());
        self::assertSame($id, $journal->id());
        self::assertSame($code, $journal->code());
        self::assertSame($type, $journal->type());
    }

    public function test_activate_and_deactivate_are_idempotent(): void
    {
        $journal = $this->createJournal();

        $journal->deactivate();
        $journal->deactivate();
        self::assertSame(JournalStatus::Inactive, $journal->status());
        self::assertFalse($journal->isActive());

        $journal->activate();
        $journal->activate();
        self::assertSame(JournalStatus::Active, $journal->status());
        self::assertTrue($journal->isActive());
    }

    public function test_it_has_no_mutation_entries_or_number_sequence_api(): void
    {
        self::assertFalse(method_exists(Journal::class, 'changeCode'));
        self::assertFalse(method_exists(Journal::class, 'changeType'));
        self::assertFalse(method_exists(Journal::class, 'entries'));
        self::assertFalse(method_exists(Journal::class, 'addEntry'));
        self::assertFalse(method_exists(Journal::class, 'numberSequence'));
    }

    private function createJournal(): Journal
    {
        return new Journal(
            new JournalId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new JournalCode('sales01'),
            new JournalName('Sales journal'),
            JournalType::Sales,
            JournalStatus::Active,
        );
    }
}
