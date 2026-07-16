<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Purchasing\ValueObjects;

use App\Domain\Purchasing\ValueObjects\SupplierReference;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SupplierReferenceTest extends TestCase
{
    public function test_it_preserves_unicode_and_uses_value_object_semantics(): void
    {
        $reference = new SupplierReference('Leveranciersfactuur ÉÉN');

        self::assertSame('Leveranciersfactuur ÉÉN', $reference->value());
        self::assertSame('Leveranciersfactuur ÉÉN', $reference->toString());
        self::assertSame('Leveranciersfactuur ÉÉN', (string) $reference);
        self::assertTrue($reference->equals(new SupplierReference('Leveranciersfactuur ÉÉN')));
        self::assertFalse($reference->equals(new SupplierReference('leveranciersfactuur ÉÉN')));
    }

    #[DataProvider('invalidReferences')]
    public function test_it_rejects_invalid_input(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SupplierReference($value);
    }

    /** @return array<string, array{string}> */
    public static function invalidReferences(): array
    {
        return [
            'empty' => [''],
            'leading whitespace' => [' Reference'],
            'trailing whitespace' => ['Reference '],
        ];
    }
}
