<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fiscal\ValueObjects;

use App\Domain\Fiscal\ValueObjects\TaxCalculationResult;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Fiscal\ValueObjects\TaxRate;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class TaxCalculationResultTest extends TestCase
{
    public function test_it_is_immutable_and_exposes_all_values(): void
    {
        $net = new Money('100', new Currency('EUR'));
        $tax = new Money('21', new Currency('EUR'));
        $gross = new Money('121', new Currency('EUR'));
        $taxCodeId = new TaxCodeId(new Uuid('550e8400-e29b-41d4-a716-446655440000'));
        $taxRate = new TaxRate('21');
        $result = new TaxCalculationResult($net, $tax, $gross, $taxCodeId, $taxRate);

        self::assertTrue((new ReflectionClass(TaxCalculationResult::class))->isReadOnly());
        self::assertSame($net, $result->netAmount());
        self::assertSame($tax, $result->taxAmount());
        self::assertSame($gross, $result->grossAmount());
        self::assertSame($taxCodeId, $result->taxCodeId());
        self::assertSame($taxRate, $result->taxRate());
    }
}
