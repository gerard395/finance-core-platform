<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fiscal\Entities;

use App\Domain\Fiscal\Entities\TaxCode;
use App\Domain\Fiscal\Enums\TaxCodeStatus;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\ValueObjects\TaxCodeCode;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Fiscal\ValueObjects\TaxCodeName;
use App\Domain\Fiscal\ValueObjects\TaxRate;
use App\Domain\Shared\Identity\Uuid;
use PHPUnit\Framework\TestCase;

final class TaxCodeTest extends TestCase
{
    public function test_it_is_constructed_with_all_values_exposed(): void
    {
        $taxCode = $this->createTaxCode();

        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $taxCode->id()->toString());
        self::assertSame('VAT21', $taxCode->code()->value());
        self::assertSame('General rate', $taxCode->name()->value());
        self::assertSame('21', $taxCode->rate()->value());
        self::assertSame(TaxPostingDirection::Output, $taxCode->direction());
        self::assertSame(TaxCodeStatus::Active, $taxCode->status());
        self::assertTrue($taxCode->isActive());
    }

    public function test_it_can_be_renamed_and_change_its_current_rate(): void
    {
        $taxCode = $this->createTaxCode();
        $id = $taxCode->id();
        $code = $taxCode->code();
        $oldRate = $taxCode->rate();

        $taxCode->rename(new TaxCodeName('Updated general rate'));
        $taxCode->changeRate(new TaxRate('22.5'));

        self::assertSame('Updated general rate', $taxCode->name()->value());
        self::assertSame('22.5', $taxCode->rate()->value());
        self::assertNotSame($oldRate, $taxCode->rate());
        self::assertSame($id, $taxCode->id());
        self::assertSame($code, $taxCode->code());
    }

    public function test_activate_and_deactivate_are_idempotent(): void
    {
        $taxCode = $this->createTaxCode();

        $taxCode->deactivate();
        $taxCode->deactivate();
        self::assertSame(TaxCodeStatus::Inactive, $taxCode->status());
        self::assertFalse($taxCode->isActive());

        $taxCode->activate();
        $taxCode->activate();
        self::assertSame(TaxCodeStatus::Active, $taxCode->status());
        self::assertTrue($taxCode->isActive());
    }

    public function test_it_has_no_history_country_or_tax_return_api(): void
    {
        self::assertFalse(method_exists(TaxCode::class, 'rateHistory'));
        self::assertFalse(method_exists(TaxCode::class, 'countryCode'));
        self::assertFalse(method_exists(TaxCode::class, 'taxReturn'));
    }

    private function createTaxCode(): TaxCode
    {
        return new TaxCode(
            new TaxCodeId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new TaxCodeCode('vat21'),
            new TaxCodeName('General rate'),
            new TaxRate('21.0000'),
            TaxPostingDirection::Output,
            TaxCodeStatus::Active,
        );
    }
}
