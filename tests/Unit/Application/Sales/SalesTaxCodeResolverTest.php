<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Sales;

use App\Application\Fiscal\TaxCodeReadRepository;
use App\Application\Fiscal\TaxCodeSelectionItem;
use App\Application\Sales\SalesTaxCodeResolutionStatus;
use App\Application\Sales\SalesTaxCodeResolver;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Enums\TaxCodeStatus;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\ValueObjects\TaxCodeCode;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Fiscal\ValueObjects\TaxCodeName;
use App\Domain\Fiscal\ValueObjects\TaxRate;
use App\Domain\Shared\Identity\Uuid;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SalesTaxCodeResolverTest extends TestCase
{
    public function test_it_resolves_an_active_output_code_with_catalogue_rate(): void
    {
        $item = $this->item(TaxPostingDirection::Output, TaxCodeStatus::Active, '21.1250');
        $result = (new SalesTaxCodeResolver($this->repository($item)))->resolve($this->administration(), $item->id());

        self::assertSame(SalesTaxCodeResolutionStatus::Success, $result->status());
        self::assertSame('21.125', $result->taxCode()?->rate()->value());
        self::assertSame(TaxPostingDirection::Output, $result->taxCode()?->direction());
    }

    #[DataProvider('failures')]
    public function test_it_returns_typed_failures(
        ?TaxPostingDirection $direction,
        ?TaxCodeStatus $status,
        SalesTaxCodeResolutionStatus $expected,
    ): void {
        $item = $direction === null || $status === null ? null : $this->item($direction, $status);
        $result = (new SalesTaxCodeResolver($this->repository($item)))->resolve(
            $this->administration(),
            $this->taxCodeId(),
        );

        self::assertSame($expected, $result->status());
        self::assertNull($result->taxCode());
    }

    /** @return iterable<string, array{?TaxPostingDirection, ?TaxCodeStatus, SalesTaxCodeResolutionStatus}> */
    public static function failures(): iterable
    {
        yield 'unknown or cross-tenant' => [null, null, SalesTaxCodeResolutionStatus::NotFound];
        yield 'inactive' => [TaxPostingDirection::Output, TaxCodeStatus::Inactive, SalesTaxCodeResolutionStatus::Inactive];
        yield 'input direction' => [TaxPostingDirection::Input, TaxCodeStatus::Active, SalesTaxCodeResolutionStatus::WrongDirection];
    }

    private function repository(?TaxCodeSelectionItem $item): TaxCodeReadRepository
    {
        return new class($item) implements TaxCodeReadRepository
        {
            public function __construct(private readonly ?TaxCodeSelectionItem $item) {}

            public function findActiveForAdministrationAndDirection(AdministrationId $administrationId, TaxPostingDirection $direction): array
            {
                return [];
            }

            public function findByIdForAdministration(AdministrationId $administrationId, TaxCodeId $taxCodeId): ?TaxCodeSelectionItem
            {
                return $this->item;
            }
        };
    }

    private function item(
        TaxPostingDirection $direction,
        TaxCodeStatus $status,
        string $rate = '21',
    ): TaxCodeSelectionItem {
        return new TaxCodeSelectionItem(
            $this->taxCodeId(),
            new TaxCodeCode('VAT21'),
            new TaxCodeName('Output VAT'),
            new TaxRate($rate),
            $direction,
            $status,
        );
    }

    private function administration(): AdministrationId
    {
        return new AdministrationId(new Uuid('10000000-0000-4000-8000-000000000001'));
    }

    private function taxCodeId(): TaxCodeId
    {
        return new TaxCodeId(new Uuid('20000000-0000-4000-8000-000000000001'));
    }
}
