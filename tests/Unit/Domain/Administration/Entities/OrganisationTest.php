<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Administration\Entities;

use App\Domain\Administration\Entities\Organisation;
use App\Domain\Administration\ValueObjects\OrganisationId;
use App\Domain\Shared\Identity\Uuid;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OrganisationTest extends TestCase
{
    public function test_it_is_constructed_with_all_values_readable(): void
    {
        $organisation = $this->createOrganisation();

        self::assertSame('Finance Core', $organisation->displayName());
        self::assertSame('Finance Core Platform B.V.', $organisation->legalName());
        self::assertSame('B.V.', $organisation->legalForm());
        self::assertSame('12345678', $organisation->chamberOfCommerceNumber());
        self::assertSame('NL123456789B01', $organisation->vatNumber());
        self::assertSame('Main Street 1, Amsterdam', $organisation->primaryAddress());
        self::assertSame('NL91ABNA0417164300', $organisation->iban());
        self::assertSame('ABNANL2A', $organisation->bic());
    }

    public function test_it_can_rename_the_display_name(): void
    {
        $organisation = $this->createOrganisation();

        $organisation->renameDisplayName('Finance Europe');

        self::assertSame('Finance Europe', $organisation->displayName());
    }

    public function test_it_can_change_and_remove_the_legal_name(): void
    {
        $organisation = $this->createOrganisation();

        $organisation->changeLegalName('Finance Europe B.V.');
        self::assertSame('Finance Europe B.V.', $organisation->legalName());

        $organisation->changeLegalName(null);
        self::assertNull($organisation->legalName());
    }

    public function test_it_rejects_an_invalid_display_name(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createOrganisation(displayName: 'A');
    }

    public function test_it_rejects_an_empty_optional_string(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createOrganisation(legalName: '');
    }

    public function test_it_rejects_leading_whitespace(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createOrganisation(displayName: ' Finance Core');
    }

    public function test_it_rejects_trailing_whitespace(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createOrganisation(vatNumber: 'NL123456789B01 ');
    }

    public function test_identity_remains_the_same_object_after_changes(): void
    {
        $organisation = $this->createOrganisation();
        $id = $organisation->id();

        $organisation->renameDisplayName('Finance Europe');
        $organisation->changeLegalName(null);

        self::assertSame($id, $organisation->id());
    }

    private function createOrganisation(
        string $displayName = 'Finance Core',
        ?string $legalName = 'Finance Core Platform B.V.',
        ?string $vatNumber = 'NL123456789B01',
    ): Organisation {
        return new Organisation(
            new OrganisationId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            $displayName,
            $legalName,
            'B.V.',
            '12345678',
            $vatNumber,
            'Main Street 1, Amsterdam',
            'NL91ABNA0417164300',
            'ABNANL2A',
        );
    }
}
