<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Banking\ValueObjects;

use App\Domain\Banking\ValueObjects\MatchingError;
use PHPUnit\Framework\TestCase;

final class MatchingErrorTest extends TestCase
{
    public function test_it_exposes_stable_code_and_message(): void
    {
        $error = new MatchingError(MatchingError::AMOUNT_MISMATCH, 'Amounts differ.');

        self::assertSame('AMOUNT_MISMATCH', $error->code());
        self::assertSame('Amounts differ.', $error->message());
        self::assertSame('NO_PAYMENTS', MatchingError::NO_PAYMENTS);
        self::assertSame('INVALID_STATUS', MatchingError::INVALID_STATUS);
    }
}
