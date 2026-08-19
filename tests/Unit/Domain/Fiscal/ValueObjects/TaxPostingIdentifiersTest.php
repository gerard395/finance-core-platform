<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fiscal\ValueObjects;

use App\Domain\Fiscal\ValueObjects\TaxPostingId;
use App\Domain\Fiscal\ValueObjects\TaxSourceDocumentId;
use App\Domain\Fiscal\ValueObjects\TaxSourceLineId;
use App\Domain\Shared\Identity\Uuid;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class TaxPostingIdentifiersTest extends TestCase
{
    /** @param class-string<TaxPostingId|TaxSourceDocumentId|TaxSourceLineId> $class */
    #[DataProvider('identifierClasses')]
    public function test_identifier_follows_immutable_uuid_pattern(string $class): void
    {
        $uuid = new Uuid('550e8400-e29b-41d4-a716-446655440000');
        $identifier = new $class($uuid);

        self::assertTrue((new ReflectionClass($class))->isReadOnly());
        self::assertSame($uuid, $identifier->uuid());
        self::assertSame($uuid->toString(), $identifier->toString());
        self::assertSame($uuid->toString(), (string) $identifier);
        self::assertTrue($identifier->equals(new $class(new Uuid($uuid->toString()))));
        self::assertFalse($identifier->equals(new $class(new Uuid('123e4567-e89b-42d3-a456-426614174000'))));
    }

    /** @return iterable<string, array{class-string<TaxPostingId|TaxSourceDocumentId|TaxSourceLineId>}> */
    public static function identifierClasses(): iterable
    {
        yield 'tax posting' => [TaxPostingId::class];
        yield 'source document' => [TaxSourceDocumentId::class];
        yield 'source line' => [TaxSourceLineId::class];
    }
}
