<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Administration\Entities;

use App\Domain\Administration\Entities\Administration;
use App\Domain\Administration\ValueObjects\AdministrationCode;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Administration\ValueObjects\AdministrationName;
use App\Domain\Administration\ValueObjects\AdministrationStatus;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Identity\Uuid;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AdministrationTest extends TestCase
{
    public function test_it_is_constructed_with_the_expected_state(): void
    {
        $administration = $this->createAdministration();

        self::assertSame('FINANCE', $administration->code()->value());
        self::assertSame('Finance Europe', $administration->name()->value());
        self::assertSame('European administration', $administration->description());
        self::assertSame('EUR', $administration->baseCurrency()->code());
        self::assertSame(AdministrationStatus::Active, $administration->status());
        self::assertTrue($administration->isActive());
    }

    public function test_identity_remains_the_same_object_after_state_changes(): void
    {
        $administration = $this->createAdministration();
        $id = $administration->id();

        $administration->rename(new AdministrationName('Finance Global'));
        $administration->changeDescription(null);
        $administration->changeBaseCurrency(new Currency('USD'));
        $administration->deactivate();

        self::assertSame($id, $administration->id());
    }

    public function test_it_can_start_inactive(): void
    {
        $administration = $this->createAdministration(AdministrationStatus::Inactive);

        self::assertFalse($administration->isActive());
    }

    public function test_it_can_be_activated_and_deactivated(): void
    {
        $administration = $this->createAdministration(AdministrationStatus::Inactive);

        $administration->activate();
        self::assertTrue($administration->isActive());

        $administration->deactivate();
        self::assertFalse($administration->isActive());
    }

    public function test_activation_and_deactivation_are_idempotent(): void
    {
        $administration = $this->createAdministration();

        $administration->activate();
        $administration->activate();
        self::assertSame(AdministrationStatus::Active, $administration->status());

        $administration->deactivate();
        $administration->deactivate();
        self::assertSame(AdministrationStatus::Inactive, $administration->status());
    }

    public function test_it_can_be_renamed_including_to_the_same_name(): void
    {
        $administration = $this->createAdministration();

        $administration->rename($administration->name());
        $administration->rename(new AdministrationName('Finance Global'));

        self::assertSame('Finance Global', $administration->name()->value());
    }

    public function test_description_can_be_changed_and_removed(): void
    {
        $administration = $this->createAdministration();

        $administration->changeDescription('Global administration');
        self::assertSame('Global administration', $administration->description());

        $administration->changeDescription(null);
        self::assertNull($administration->description());
    }

    public function test_it_rejects_an_empty_description(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createAdministration(description: '');
    }

    public function test_it_rejects_description_with_leading_whitespace(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createAdministration(description: ' Invalid');
    }

    public function test_it_rejects_description_with_trailing_whitespace(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createAdministration(description: 'Invalid ');
    }

    public function test_it_rejects_a_too_long_description(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createAdministration(description: str_repeat('A', 1001));
    }

    public function test_it_can_change_base_currency(): void
    {
        $administration = $this->createAdministration();

        $administration->changeBaseCurrency(new Currency('USD'));

        self::assertSame('USD', $administration->baseCurrency()->code());
    }

    public function test_code_has_no_mutation_method(): void
    {
        self::assertFalse(method_exists($this->createAdministration(), 'changeCode'));
    }

    private function createAdministration(
        AdministrationStatus $status = AdministrationStatus::Active,
        ?string $description = 'European administration',
    ): Administration {
        return new Administration(
            new AdministrationId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new AdministrationCode('finance'),
            new AdministrationName('Finance Europe'),
            $description,
            new Currency('EUR'),
            $status,
        );
    }
}
