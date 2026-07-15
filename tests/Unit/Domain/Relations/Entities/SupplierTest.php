<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Relations\Entities;

use App\Domain\Relations\Entities\Supplier;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Relations\ValueObjects\SupplierId;
use App\Domain\Relations\ValueObjects\SupplierNumber;
use App\Domain\Shared\Identity\Uuid;
use PHPUnit\Framework\TestCase;

final class SupplierTest extends TestCase
{
    public function test_it_is_constructed_with_the_expected_state(): void
    {
        $supplier = $this->createSupplier();

        self::assertInstanceOf(SupplierId::class, $supplier->id());
        self::assertInstanceOf(RelationId::class, $supplier->relationId());
        self::assertSame('SUPP-001', $supplier->supplierNumber()->value());
        self::assertTrue($supplier->isActive());
    }

    public function test_activate_and_deactivate_are_idempotent(): void
    {
        $supplier = $this->createSupplier();

        $supplier->deactivate();
        $supplier->deactivate();
        self::assertFalse($supplier->isActive());

        $supplier->activate();
        $supplier->activate();
        self::assertTrue($supplier->isActive());
    }

    public function test_identity_relation_and_supplier_number_remain_unchanged(): void
    {
        $supplier = $this->createSupplier();
        $id = $supplier->id();
        $relationId = $supplier->relationId();
        $supplierNumber = $supplier->supplierNumber();

        $supplier->deactivate();
        $supplier->activate();

        self::assertSame($id, $supplier->id());
        self::assertSame($relationId, $supplier->relationId());
        self::assertSame($supplierNumber, $supplier->supplierNumber());
    }

    private function createSupplier(): Supplier
    {
        return new Supplier(
            new SupplierId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new RelationId(new Uuid('550e8400-e29b-41d4-a716-446655440001')),
            new SupplierNumber('supp-001'),
            true,
        );
    }
}
