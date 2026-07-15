<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Relations\Entities;

use App\Domain\Relations\Entities\Customer;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Relations\ValueObjects\CustomerNumber;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Identity\Uuid;
use PHPUnit\Framework\TestCase;

final class CustomerTest extends TestCase
{
    public function test_it_is_constructed_with_the_expected_state(): void
    {
        $customer = $this->createCustomer();

        self::assertInstanceOf(CustomerId::class, $customer->id());
        self::assertInstanceOf(RelationId::class, $customer->relationId());
        self::assertSame('CUST-001', $customer->customerNumber()->value());
        self::assertTrue($customer->isActive());
    }

    public function test_activate_and_deactivate_are_idempotent(): void
    {
        $customer = $this->createCustomer();

        $customer->deactivate();
        $customer->deactivate();
        self::assertFalse($customer->isActive());

        $customer->activate();
        $customer->activate();
        self::assertTrue($customer->isActive());
    }

    public function test_identity_relation_and_customer_number_remain_unchanged(): void
    {
        $customer = $this->createCustomer();
        $id = $customer->id();
        $relationId = $customer->relationId();
        $customerNumber = $customer->customerNumber();

        $customer->deactivate();
        $customer->activate();

        self::assertSame($id, $customer->id());
        self::assertSame($relationId, $customer->relationId());
        self::assertSame($customerNumber, $customer->customerNumber());
    }

    private function createCustomer(): Customer
    {
        return new Customer(
            new CustomerId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new RelationId(new Uuid('550e8400-e29b-41d4-a716-446655440001')),
            new CustomerNumber('cust-001'),
            true,
        );
    }
}
