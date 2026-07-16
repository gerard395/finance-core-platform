<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Accounting\ValueObjects;

use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Shared\Identity\Uuid;
use PHPUnit\Framework\TestCase;

final class JournalEntryIdTest extends TestCase
{
    public function test_it_wraps_a_uuid_and_exposes_string_representations(): void
    {
        $uuid = new Uuid('550e8400-e29b-41d4-a716-446655440000');
        $id = new JournalEntryId($uuid);

        self::assertSame($uuid, $id->uuid());
        self::assertSame($uuid->toString(), $id->toString());
        self::assertSame($uuid->toString(), (string) $id);
    }

    public function test_equality_uses_the_wrapped_uuid(): void
    {
        $id = new JournalEntryId(new Uuid('550e8400-e29b-41d4-a716-446655440000'));

        self::assertTrue($id->equals(new JournalEntryId(new Uuid('550e8400-e29b-41d4-a716-446655440000'))));
        self::assertFalse($id->equals(new JournalEntryId(new Uuid('123e4567-e89b-42d3-a456-426614174000'))));
    }
}
