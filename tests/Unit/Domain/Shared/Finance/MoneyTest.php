<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared\Finance;

use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use DomainException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_constructor_exposes_amount_and_same_currency(): void
    {
        $currency = new Currency('EUR');
        $money = new Money('125.50', $currency);

        self::assertSame('125.5', $money->amount());
        self::assertSame($currency, $money->currency());
        self::assertFalse($money->isZero());
    }

    public function test_equality_uses_normalized_amount_and_currency(): void
    {
        $money = new Money('10.00', new Currency('EUR'));

        self::assertTrue($money->equals(new Money('10.0', new Currency('eur'))));
        self::assertFalse($money->equals(new Money('10.01', new Currency('EUR'))));
        self::assertFalse($money->equals(new Money('10.00', new Currency('USD'))));
    }

    public function test_zero_creates_canonical_zero_for_currency(): void
    {
        $currency = new Currency('EUR');
        $zero = Money::zero($currency);

        self::assertSame('0', $zero->amount());
        self::assertSame($currency, $zero->currency());
        self::assertTrue($zero->isZero());
        self::assertTrue($zero->equals(new Money('-0.000', new Currency('EUR'))));
    }

    #[DataProvider('signedAmounts')]
    public function test_sign_queries_use_the_canonical_amount(
        string $amount,
        string $expectedAmount,
        bool $isPositive,
        bool $isNegative,
    ): void {
        $money = new Money($amount, new Currency('EUR'));

        self::assertSame($expectedAmount, $money->amount());
        self::assertSame($isPositive, $money->isPositive());
        self::assertSame($isNegative, $money->isNegative());
    }

    /** @return array<string, array{string, string, bool, bool}> */
    public static function signedAmounts(): array
    {
        return [
            'positive amount' => ['10', '10', true, false],
            'negative amount' => ['-10', '-10', false, true],
            'canonical zero' => ['-0.000', '0', false, false],
            'normalized positive decimal' => ['12.5000', '12.5', true, false],
            'normalized negative decimal' => ['-12.5000', '-12.5', false, true],
        ];
    }

    public function test_sign_queries_do_not_mutate_money(): void
    {
        $money = new Money('-12.5000', new Currency('EUR'));

        self::assertFalse($money->isPositive());
        self::assertTrue($money->isNegative());
        self::assertSame('-12.5', $money->amount());
    }

    public function test_multiply_by_integer_preserves_currency(): void
    {
        $currency = new Currency('EUR');
        $result = (new Money('12.5', $currency))->multiply('3');

        self::assertSame('37.5', $result->amount());
        self::assertSame($currency, $result->currency());
    }

    public function test_multiply_by_decimal_is_exact_without_floating_point_deviation(): void
    {
        $result = (new Money('0.1', new Currency('EUR')))->multiply('0.2');

        self::assertSame('0.02', $result->amount());
    }

    public function test_multiply_by_zero_returns_canonical_zero(): void
    {
        $result = (new Money('12.5', new Currency('EUR')))->multiply('0');

        self::assertSame('0', $result->amount());
        self::assertTrue($result->isZero());
    }

    #[DataProvider('additions')]
    public function test_add_is_exact_for_signed_decimal_amounts(string $left, string $right, string $expected): void
    {
        $result = (new Money($left, new Currency('EUR')))->add(new Money($right, new Currency('EUR')));

        self::assertSame($expected, $result->amount());
        self::assertSame('EUR', $result->currency()->code());
    }

    /** @return array<string, array{string, string, string}> */
    public static function additions(): array
    {
        return [
            'positive amounts' => ['12.5', '7.25', '19.75'],
            'negative amounts' => ['-12.5', '-7.25', '-19.75'],
            'positive and smaller negative' => ['12.5', '-7.25', '5.25'],
            'positive and larger negative' => ['7.25', '-12.5', '-5.25'],
            'exact decimals' => ['0.1', '0.2', '0.3'],
            'maximum precision' => ['0.00000001', '0.00000002', '0.00000003'],
            'zero' => ['12.5', '0', '12.5'],
            'canonical zero' => ['12.5', '-12.5', '0'],
            'arbitrary size' => ['999999999999999999.99', '0.01', '1000000000000000000'],
        ];
    }

    public function test_add_rejects_different_currencies(): void
    {
        $this->expectException(DomainException::class);

        (new Money('10', new Currency('EUR')))->add(new Money('10', new Currency('USD')));
    }

    #[DataProvider('subtractions')]
    public function test_subtract_is_exact_for_signed_decimal_amounts(string $left, string $right, string $expected): void
    {
        $currency = new Currency('EUR');
        $result = (new Money($left, $currency))->subtract(new Money($right, $currency));

        self::assertSame($expected, $result->amount());
        self::assertSame($currency, $result->currency());
    }

    /** @return array<string, array{string, string, string}> */
    public static function subtractions(): array
    {
        return [
            'positive minus positive' => ['12.5', '7.25', '5.25'],
            'positive minus larger positive' => ['7.25', '12.5', '-5.25'],
            'negative minus positive' => ['-7.25', '12.5', '-19.75'],
            'negative minus negative' => ['-7.25', '-12.5', '5.25'],
            'amount minus zero' => ['12.5', '0', '12.5'],
            'amount minus itself is canonical zero' => ['12.5', '12.5', '0'],
            'exact decimals' => ['0.3', '0.2', '0.1'],
            'maximum precision' => ['0.00000003', '0.00000002', '0.00000001'],
        ];
    }

    public function test_subtract_rejects_different_currencies(): void
    {
        $this->expectException(DomainException::class);

        (new Money('10', new Currency('EUR')))->subtract(new Money('2', new Currency('USD')));
    }

    public function test_subtract_is_immutable(): void
    {
        $money = new Money('10', new Currency('EUR'));
        $other = new Money('2.5', new Currency('EUR'));

        $result = $money->subtract($other);

        self::assertNotSame($money, $result);
        self::assertSame('7.5', $result->amount());
        self::assertSame('10', $money->amount());
        self::assertSame('2.5', $other->amount());
    }

    #[DataProvider('absoluteAmounts')]
    public function test_absolute_preserves_currency_and_returns_canonical_amount(string $amount, string $expected): void
    {
        $currency = new Currency('EUR');
        $result = (new Money($amount, $currency))->absolute();

        self::assertSame($expected, $result->amount());
        self::assertSame($currency, $result->currency());
    }

    /** @return array<string, array{string, string}> */
    public static function absoluteAmounts(): array
    {
        return [
            'positive' => ['12.5', '12.5'],
            'negative' => ['-12.5', '12.5'],
            'zero' => ['-0.000', '0'],
        ];
    }

    public function test_add_and_absolute_are_immutable(): void
    {
        $money = new Money('-10', new Currency('EUR'));
        $other = new Money('2.5', new Currency('EUR'));

        self::assertSame('-7.5', $money->add($other)->amount());
        self::assertSame('10', $money->absolute()->amount());
        self::assertSame('-10', $money->amount());
        self::assertSame('2.5', $other->amount());
    }

    #[DataProvider('invalidMultipliers')]
    public function test_invalid_multiplier_is_rejected(string $multiplier): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Money('10', new Currency('EUR')))->multiply($multiplier);
    }

    /** @return array<string, array{string}> */
    public static function invalidMultipliers(): array
    {
        return [
            'empty' => [''],
            'negative' => ['-1'],
            'leading zero' => ['01.5'],
            'float notation' => ['1,5'],
            'scientific notation' => ['1e2'],
            'more than eight decimals' => ['1.123456789'],
        ];
    }

    #[DataProvider('invalidAmounts')]
    public function test_invalid_amount_is_rejected(string $amount): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Money($amount, new Currency('EUR'));
    }

    /** @return array<string, array{string}> */
    public static function invalidAmounts(): array
    {
        return [
            'empty' => [''],
            'float notation' => ['1,50'],
            'leading zero' => ['01.50'],
            'trailing decimal point' => ['1.'],
            'more than eight decimals' => ['1.123456789'],
            'surrounding whitespace' => [' 1.50 '],
            'scientific notation' => ['1e2'],
        ];
    }
}
