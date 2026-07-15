<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Administration\ValueObjects;

use App\Domain\Administration\ValueObjects\AdministrationName;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AdministrationNameTest extends TestCase
{
    public function test_it_accepts_a_valid_name_and_preserves_its_casing(): void
    {
        self::assertSame('Finance Europe', new AdministrationName('Finance Europe')->value());
    }

    public function test_it_accepts_unicode(): void
    {
        self::assertSame('Financiën 日本', new AdministrationName('Financiën 日本')->value());
    }

    public function test_equality_and_string_representations_use_the_original_value(): void
    {
        $name = new AdministrationName('Finance Europe');

        self::assertTrue($name->equals(new AdministrationName('Finance Europe')));
        self::assertFalse($name->equals(new AdministrationName('finance europe')));
        self::assertSame('Finance Europe', $name->toString());
        self::assertSame('Finance Europe', (string) $name);
    }

    public function test_it_rejects_an_empty_name(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AdministrationName('');
    }

    public function test_it_rejects_leading_whitespace(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AdministrationName(' Finance');
    }

    public function test_it_rejects_trailing_whitespace(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AdministrationName('Finance ');
    }

    public function test_it_rejects_a_too_short_name(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AdministrationName('A');
    }

    public function test_it_accepts_the_maximum_length(): void
    {
        self::assertSame(255, mb_strlen(new AdministrationName(str_repeat('A', 255))->value()));
    }

    public function test_it_rejects_a_too_long_name(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AdministrationName(str_repeat('A', 256));
    }
}
