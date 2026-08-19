<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Accounting\Requests;

use App\Domain\Accounting\Entities\JournalEntryLine;
use App\Domain\Accounting\Requests\PostingRequest;
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
use ReflectionClass;

final class PostingRequestTest extends TestCase
{
    public function test_it_is_constructed_and_exposes_all_values(): void
    {
        $administrationId = new AdministrationId(new Uuid('123e4567-e89b-42d3-a456-426614174001'));
        $journalId = new JournalId(new Uuid('550e8400-e29b-41d4-a716-446655440000'));
        $postingDate = new PostingDate(new DateTimeImmutable('2026-07-16'));
        $reference = new JournalEntryReference('Sales invoice 2026-001');
        $line = $this->createLine();
        $request = new PostingRequest($administrationId, $journalId, $postingDate, $reference, [$line]);

        self::assertSame($administrationId, $request->administrationId());
        self::assertSame($journalId, $request->journalId());
        self::assertSame($postingDate, $request->postingDate());
        self::assertSame($reference, $request->reference());
        self::assertSame([$line], $request->lines());
    }

    public function test_it_is_immutable_and_defensively_owns_the_lines_list(): void
    {
        $line = $this->createLine();
        $lines = [$line];
        $request = $this->createRequest($lines);

        $lines[] = $this->createLine(
            '936da01f-9abd-4d9d-80c7-02af85c822a8',
            '6ba7b810-9dad-41d1-80b4-00c04fd430c8',
        );
        $exposedLines = $request->lines();
        array_pop($exposedLines);

        self::assertTrue((new ReflectionClass(PostingRequest::class))->isReadOnly());
        self::assertSame([$line], $request->lines());
    }

    public function test_it_performs_no_collection_validation(): void
    {
        $request = $this->createRequest([]);

        self::assertSame([], $request->lines());
    }

    /** @param list<JournalEntryLine> $lines */
    private function createRequest(array $lines): PostingRequest
    {
        return new PostingRequest(
            new AdministrationId(new Uuid('123e4567-e89b-42d3-a456-426614174001')),
            new JournalId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new PostingDate(new DateTimeImmutable('2026-07-16')),
            new JournalEntryReference('Sales invoice 2026-001'),
            $lines,
        );
    }

    private function createLine(
        string $lineId = '123e4567-e89b-42d3-a456-426614174000',
        string $ledgerAccountId = '6ba7b811-9dad-41d1-80b4-00c04fd430c8',
    ): JournalEntryLine {
        return new JournalEntryLine(
            new JournalEntryLineId(new Uuid($lineId)),
            new LedgerAccountId(new Uuid($ledgerAccountId)),
            new Money('100', new Currency('EUR')),
            null,
            'Sales invoice',
        );
    }
}
