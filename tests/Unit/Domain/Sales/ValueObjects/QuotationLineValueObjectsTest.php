<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sales\ValueObjects;

use App\Domain\Sales\ValueObjects\LineDescription;
use App\Domain\Sales\ValueObjects\Quantity;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class QuotationLineValueObjectsTest extends TestCase
{
    public function test_quantity_is_normalized_and_description_is_required(): void
    {
        self::assertSame('2.5', new Quantity('2.500')->value());
        self::assertSame('Consulting', new LineDescription('Consulting')->value());
    }

    public function test_invalid_description_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new LineDescription('');
    }
}
