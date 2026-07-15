<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Administration\ValueObjects;

use App\Domain\Administration\ValueObjects\NumberSequenceId;
use App\Domain\Shared\Identity\Uuid;
use PHPUnit\Framework\TestCase;

final class NumberSequenceIdTest extends TestCase
{
    public function test_constructor_accepts_a_uuid(): void
    {
        $numberSequenceId = new NumberSequenceId(
            new Uuid('550e8400-e29b-41d4-a716-446655440000'),
        );

        self::assertInstanceOf(Uuid::class, $numberSequenceId->uuid());
    }

    public function test_uuid_returns_the_same_object(): void
    {
        $uuid = new Uuid('550e8400-e29b-41d4-a716-446655440000');

        self::assertSame($uuid, new NumberSequenceId($uuid)->uuid());
    }

    public function test_equal_number_sequence_ids_compare_as_equal(): void
    {
        $numberSequenceId = new NumberSequenceId(new Uuid('550e8400-e29b-41d4-a716-446655440000'));
        $sameNumberSequenceId = new NumberSequenceId(new Uuid('550E8400-E29B-41D4-A716-446655440000'));

        self::assertTrue($numberSequenceId->equals($sameNumberSequenceId));
    }

    public function test_different_number_sequence_ids_compare_as_unequal(): void
    {
        $numberSequenceId = new NumberSequenceId(new Uuid('550e8400-e29b-41d4-a716-446655440000'));
        $otherNumberSequenceId = new NumberSequenceId(new Uuid('550e8400-e29b-41d4-a716-446655440001'));

        self::assertFalse($numberSequenceId->equals($otherNumberSequenceId));
    }

    public function test_string_representations_match_the_uuid(): void
    {
        $uuid = new Uuid('550E8400-E29B-41D4-A716-446655440000');
        $numberSequenceId = new NumberSequenceId($uuid);

        self::assertSame($uuid->toString(), $numberSequenceId->toString());
        self::assertSame($uuid->toString(), (string) $numberSequenceId);
    }
}
