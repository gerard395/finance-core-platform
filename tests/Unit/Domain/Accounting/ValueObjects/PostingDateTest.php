<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Accounting\ValueObjects;

use App\Domain\Accounting\ValueObjects\PostingDate;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PostingDateTest extends TestCase
{
    public function test_it_preserves_the_same_datetime_immutable_instance(): void
    {
        $date = new DateTimeImmutable('2026-07-16 10:30:00+02:00');
        $postingDate = new PostingDate($date);

        self::assertSame($date, $postingDate->value());
    }

    public function test_equality_compares_datetime_values(): void
    {
        $postingDate = new PostingDate(new DateTimeImmutable('2026-07-16 10:30:00+02:00'));

        self::assertTrue($postingDate->equals(new PostingDate(new DateTimeImmutable('2026-07-16 10:30:00+02:00'))));
        self::assertFalse($postingDate->equals(new PostingDate(new DateTimeImmutable('2026-07-17 10:30:00+02:00'))));
    }

    public function test_datetime_modification_does_not_change_the_stored_value(): void
    {
        $date = new DateTimeImmutable('2026-07-16');
        $postingDate = new PostingDate($date);
        $changedDate = $postingDate->value()->modify('+1 day');

        self::assertSame('2026-07-16', $postingDate->value()->format('Y-m-d'));
        self::assertSame('2026-07-17', $changedDate->format('Y-m-d'));
        self::assertNotSame($postingDate->value(), $changedDate);
    }
}
