<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence;

use App\Application\Fiscal\TaxCodeReadRepository;
use App\Application\Fiscal\TaxCodeStore;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Entities\TaxCode;
use App\Domain\Fiscal\Enums\TaxCodeStatus;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\ValueObjects\TaxCodeCode;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Fiscal\ValueObjects\TaxCodeName;
use App\Domain\Fiscal\ValueObjects\TaxRate;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\EloquentTaxCodeRepository;
use App\Infrastructure\Persistence\Eloquent\Models\AdministrationRecord;
use App\Infrastructure\Persistence\Eloquent\Models\TaxCodeRecord;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentTaxCodePersistenceTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_A = '10000000-0000-4000-8000-000000000001';

    private const ADMIN_B = '20000000-0000-4000-8000-000000000001';

    private EloquentTaxCodeRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentTaxCodeRepository;
        $this->createAdministration(self::ADMIN_A, 'A');
        $this->createAdministration(self::ADMIN_B, 'B');
    }

    public function test_exact_roundtrip_current_rate_mutation_and_status_are_persisted(): void
    {
        $taxCode = $this->taxCode(1, 'VATOUT', '21.1250', TaxPostingDirection::Output, TaxCodeStatus::Active);
        $this->repository->save($this->administration(self::ADMIN_A), $taxCode);

        $read = $this->repository->findByIdForAdministration($this->administration(self::ADMIN_A), $taxCode->id());
        self::assertNotNull($read);
        self::assertSame('VATOUT', $read->code()->value());
        self::assertSame('Output tax', $read->name()->value());
        self::assertSame('21.125', $read->rate()->value());
        self::assertSame(TaxPostingDirection::Output, $read->direction());
        self::assertSame(TaxCodeStatus::Active, $read->status());

        $taxCode->changeRate(new TaxRate('19.8765'));
        $taxCode->deactivate();
        $this->repository->save($this->administration(self::ADMIN_A), $taxCode);
        $changed = $this->repository->findByIdForAdministration($this->administration(self::ADMIN_A), $taxCode->id());

        self::assertSame('19.8765', $changed?->rate()->value());
        self::assertSame(TaxCodeStatus::Inactive, $changed?->status());
        self::assertSame(1, TaxCodeRecord::query()->count());
    }

    public function test_selection_is_active_direction_and_tenant_scoped_with_deterministic_order(): void
    {
        foreach ([
            [$this->taxCode(3, 'ZOUT', '5', TaxPostingDirection::Output), self::ADMIN_A],
            [$this->taxCode(2, 'AOUT', '9', TaxPostingDirection::Output), self::ADMIN_A],
            [$this->taxCode(1, 'INPUT', '21', TaxPostingDirection::Input), self::ADMIN_A],
            [$this->taxCode(4, 'INACTIVE', '21', TaxPostingDirection::Output, TaxCodeStatus::Inactive), self::ADMIN_A],
            [$this->taxCode(5, 'OTHER', '21', TaxPostingDirection::Output), self::ADMIN_B],
        ] as [$taxCode, $administration]) {
            $this->repository->save($this->administration($administration), $taxCode);
        }

        $items = $this->repository->findActiveForAdministrationAndDirection(
            $this->administration(self::ADMIN_A),
            TaxPostingDirection::Output,
        );

        self::assertSame(['AOUT', 'ZOUT'], array_map(static fn ($item): string => $item->code()->value(), $items));
        self::assertNull($this->repository->findByIdForAdministration($this->administration(self::ADMIN_B), $this->taxCode(2, 'AOUT', '9', TaxPostingDirection::Output)->id()));
    }

    public function test_identity_cannot_move_tenants_and_code_is_unique_per_tenant(): void
    {
        $taxCode = $this->taxCode(1, 'VATOUT', '21', TaxPostingDirection::Output);
        $this->repository->save($this->administration(self::ADMIN_A), $taxCode);

        try {
            $this->repository->save($this->administration(self::ADMIN_B), $taxCode);
            self::fail('A TaxCode identity must not move between Administrations.');
        } catch (DomainException) {
            self::assertSame(1, TaxCodeRecord::query()->count());
        }

        $this->expectException(QueryException::class);
        $this->repository->save(
            $this->administration(self::ADMIN_A),
            $this->taxCode(2, 'VATOUT', '9', TaxPostingDirection::Output),
        );
    }

    public function test_same_code_is_allowed_for_different_tenants_and_contracts_are_bound(): void
    {
        $this->repository->save($this->administration(self::ADMIN_A), $this->taxCode(1, 'VATOUT', '21', TaxPostingDirection::Output));
        $this->repository->save($this->administration(self::ADMIN_B), $this->taxCode(2, 'VATOUT', '9', TaxPostingDirection::Output));

        self::assertSame(2, TaxCodeRecord::query()->count());
        self::assertInstanceOf(EloquentTaxCodeRepository::class, $this->app->make(TaxCodeReadRepository::class));
        self::assertInstanceOf(EloquentTaxCodeRepository::class, $this->app->make(TaxCodeStore::class));
    }

    private function taxCode(
        int $id,
        string $code,
        string $rate,
        TaxPostingDirection $direction,
        TaxCodeStatus $status = TaxCodeStatus::Active,
    ): TaxCode {
        return new TaxCode(
            new TaxCodeId(new Uuid(sprintf('30000000-0000-4000-8000-%012d', $id))),
            new TaxCodeCode($code),
            new TaxCodeName($direction === TaxPostingDirection::Output ? 'Output tax' : 'Input tax'),
            new TaxRate($rate),
            $direction,
            $status,
        );
    }

    private function administration(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }

    private function createAdministration(string $id, string $suffix): void
    {
        AdministrationRecord::query()->create([
            'id' => $id,
            'code' => 'ADMIN-'.$suffix,
            'name' => 'Administration '.$suffix,
            'base_currency' => 'EUR',
            'status' => 'active',
        ]);
    }
}
