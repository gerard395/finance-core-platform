<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Accounting\Services;

use App\Domain\Accounting\Entities\JournalEntryLine;
use App\Domain\Accounting\Requests\PostingRequest;
use App\Domain\Accounting\Services\PostingEngine;
use App\Domain\Accounting\Services\PostingValidation;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\JournalEntryReference;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PostingEngineTest extends TestCase
{
    public function test_a_valid_request_creates_and_returns_a_posted_journal_entry(): void
    {
        $journalEntryId = new JournalEntryId(new Uuid('550e8400-e29b-41d4-a716-446655440000'));
        $request = $this->request([
            $this->line('123e4567-e89b-42d3-a456-426614174000', '100', null),
            $this->line('6ba7b811-9dad-41d1-80b4-00c04fd430c8', null, '100'),
        ]);
        $result = $this->engine($journalEntryId)->post($request);
        $entry = $result->journalEntry();

        self::assertTrue($result->isSuccess());
        self::assertSame([], $result->validationErrors());
        self::assertNotNull($entry);
        self::assertSame($journalEntryId, $entry->id());
        self::assertSame($request->administrationId(), $entry->administrationId());
        self::assertSame($request->journalId(), $entry->journalId());
        self::assertSame($request->postingDate(), $entry->postingDate());
        self::assertSame($request->reference(), $entry->reference());
        self::assertSame($request->lines(), $entry->lines());
        self::assertTrue($entry->isPosted());
    }

    public function test_an_invalid_request_returns_validation_errors_without_creating_an_entry(): void
    {
        $factoryCalls = 0;
        $engine = new PostingEngine(
            new PostingValidation,
            function () use (&$factoryCalls): JournalEntryId {
                $factoryCalls++;

                return new JournalEntryId(new Uuid('550e8400-e29b-41d4-a716-446655440000'));
            },
        );

        $result = $engine->post($this->request([]));

        self::assertFalse($result->isSuccess());
        self::assertNull($result->journalEntry());
        self::assertSame(['minimum_lines'], array_map(
            static fn ($error): string => $error->code(),
            $result->validationErrors(),
        ));
        self::assertSame(0, $factoryCalls);
    }

    private function engine(JournalEntryId $id): PostingEngine
    {
        return new PostingEngine(
            new PostingValidation,
            static fn (): JournalEntryId => $id,
        );
    }

    /** @param list<JournalEntryLine> $lines */
    private function request(array $lines): PostingRequest
    {
        return new PostingRequest(
            new AdministrationId(new Uuid('123e4567-e89b-42d3-a456-426614174001')),
            new JournalId(new Uuid('936da01f-9abd-4d9d-80c7-02af85c822a8')),
            new PostingDate(new DateTimeImmutable('2026-07-16')),
            new JournalEntryReference('Posting engine test'),
            $lines,
        );
    }

    private function line(string $id, ?string $debit, ?string $credit): JournalEntryLine
    {
        $currency = new Currency('EUR');

        return new JournalEntryLine(
            new JournalEntryLineId(new Uuid($id)),
            new LedgerAccountId(new Uuid('6ba7b810-9dad-41d1-80b4-00c04fd430c8')),
            $debit === null ? null : new Money($debit, $currency),
            $credit === null ? null : new Money($credit, $currency),
            'Posting line',
        );
    }
}
