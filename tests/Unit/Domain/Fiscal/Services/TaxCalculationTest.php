<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fiscal\Services;

use App\Domain\Fiscal\Entities\TaxCode;
use App\Domain\Fiscal\Enums\TaxCodeStatus;
use App\Domain\Fiscal\Services\TaxCalculation;
use App\Domain\Fiscal\ValueObjects\TaxCodeCode;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Fiscal\ValueObjects\TaxCodeName;
use App\Domain\Fiscal\ValueObjects\TaxRate;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DomainException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TaxCalculationTest extends TestCase
{
    #[DataProvider('calculations')]
    public function test_it_calculates_exact_tax_and_gross_amounts(
        string $net,
        string $rate,
        string $expectedTax,
        string $expectedGross,
    ): void {
        $result = (new TaxCalculation)->calculate(
            new Money($net, new Currency('EUR')),
            $this->taxCode($rate),
        );

        self::assertSame($net, $result->netAmount()->amount());
        self::assertSame($expectedTax, $result->taxAmount()->amount());
        self::assertSame($expectedGross, $result->grossAmount()->amount());
        self::assertSame($rate, $result->taxRate()->value());
        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $result->taxCodeId()->toString());
    }

    /** @return array<string, array{string, string, string, string}> */
    public static function calculations(): array
    {
        return [
            '21 percent' => ['100', '21', '21', '121'],
            '9 percent' => ['100', '9', '9', '109'],
            '0 percent' => ['100', '0', '0', '100'],
            'exact decimal without float drift' => ['0.1', '21', '0.021', '0.121'],
        ];
    }

    public function test_it_preserves_currency(): void
    {
        $result = (new TaxCalculation)->calculate(
            new Money('50', new Currency('USD')),
            $this->taxCode('9'),
        );

        self::assertSame('USD', $result->netAmount()->currency()->code());
        self::assertSame('USD', $result->taxAmount()->currency()->code());
        self::assertSame('USD', $result->grossAmount()->currency()->code());
    }

    public function test_it_rejects_an_inactive_tax_code(): void
    {
        $this->expectException(DomainException::class);

        (new TaxCalculation)->calculate(
            new Money('100', new Currency('EUR')),
            $this->taxCode('21', TaxCodeStatus::Inactive),
        );
    }

    public function test_it_does_not_round_an_unrepresentable_result(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new TaxCalculation)->calculate(
            new Money('0.00000001', new Currency('EUR')),
            $this->taxCode('21.1234'),
        );
    }

    private function taxCode(string $rate, TaxCodeStatus $status = TaxCodeStatus::Active): TaxCode
    {
        return new TaxCode(
            new TaxCodeId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new TaxCodeCode('vat21'),
            new TaxCodeName('General rate'),
            new TaxRate($rate),
            $status,
        );
    }
}
