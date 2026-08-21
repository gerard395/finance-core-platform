<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Sales;

use App\Application\Sales\SalesCustomerContext;
use App\Application\Sales\SalesCustomerContextReader;
use App\Application\Sales\SalesCustomerContextStatus;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\AddressId;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\AdministrationRecord;
use App\Infrastructure\Persistence\Eloquent\Models\CustomerRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RelationAddressRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RelationRecord;
use App\Infrastructure\Sales\EloquentSalesCustomerContextReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentSalesCustomerContextReaderTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_A = '91000000-0000-4000-8000-000000000001';

    private const ADMIN_B = '92000000-0000-4000-8000-000000000001';

    private const CUSTOMER = '93000000-0000-4000-8000-000000000001';

    private const RELATION = '94000000-0000-4000-8000-000000000001';

    private const INVOICE_ADDRESS = '95000000-0000-4000-8000-000000000001';

    private EloquentSalesCustomerContextReader $reader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reader = new EloquentSalesCustomerContextReader;
        $this->administration(self::ADMIN_A, 'A');
        $this->administration(self::ADMIN_B, 'B');
        RelationRecord::query()->create(['id' => self::RELATION, 'administration_id' => self::ADMIN_A, 'code' => 'REL-1', 'display_name' => 'Original name', 'active' => true]);
        CustomerRecord::query()->create(['id' => self::CUSTOMER, 'administration_id' => self::ADMIN_A, 'relation_id' => self::RELATION, 'customer_number' => 'C000001', 'active' => true]);
        $this->address(self::INVOICE_ADDRESS, 'invoice', true);
        $this->address('95000000-0000-4000-8000-000000000002', 'visiting', true);
    }

    public function test_active_same_tenant_customer_and_explicit_invoice_address_are_snapshotted(): void
    {
        $result = $this->read(self::ADMIN_A, self::INVOICE_ADDRESS);
        self::assertSame(SalesCustomerContextStatus::Success, $result->status());
        self::assertSame('C000001', $result->customer()?->customerNumber()->value());
        self::assertSame('Original name', $result->customer()?->displayName()->value());
        self::assertSame('Main street 1', $result->invoiceAddress()?->addressLine()->value());

        RelationRecord::query()->whereKey(self::RELATION)->update(['display_name' => 'Renamed', 'active' => false]);
        RelationAddressRecord::query()->whereKey(self::INVOICE_ADDRESS)->update(['address_line_1' => 'Changed 9', 'active' => false]);
        self::assertSame('Original name', $result->customer()?->displayName()->value());
        self::assertSame('Main street 1', $result->invoiceAddress()?->addressLine()->value());
    }

    public function test_inactive_and_cross_tenant_customer_are_rejected(): void
    {
        self::assertSame(SalesCustomerContextStatus::NotFound, $this->read(self::ADMIN_B, null)->status());
        CustomerRecord::query()->whereKey(self::CUSTOMER)->update(['active' => false]);
        self::assertSame(SalesCustomerContextStatus::InactiveCustomer, $this->read(self::ADMIN_A, null)->status());
    }

    public function test_fake_inactive_or_non_invoice_address_has_no_fallback(): void
    {
        self::assertSame(SalesCustomerContextStatus::MissingInvoiceAddress, $this->read(self::ADMIN_A, '95000000-0000-4000-8000-000000000099')->status());
        self::assertSame(SalesCustomerContextStatus::MissingInvoiceAddress, $this->read(self::ADMIN_A, '95000000-0000-4000-8000-000000000002')->status());
        RelationAddressRecord::query()->whereKey(self::INVOICE_ADDRESS)->update(['active' => false]);
        self::assertSame(SalesCustomerContextStatus::MissingInvoiceAddress, $this->read(self::ADMIN_A, self::INVOICE_ADDRESS)->status());
    }

    public function test_contract_is_bound(): void
    {
        self::assertInstanceOf(EloquentSalesCustomerContextReader::class, $this->app->make(SalesCustomerContextReader::class));
    }

    private function read(string $administration, ?string $address): SalesCustomerContext
    {
        return $this->reader->read(new AdministrationId(new Uuid($administration)), new CustomerId(new Uuid(self::CUSTOMER)), $address === null ? null : new AddressId(new Uuid($address)));
    }

    private function administration(string $id, string $suffix): void
    {
        AdministrationRecord::query()->create(['id' => $id, 'code' => 'ADMIN-'.$suffix, 'name' => 'Administration '.$suffix, 'base_currency' => 'EUR', 'status' => 'active']);
    }

    private function address(string $id, string $type, bool $active): void
    {
        RelationAddressRecord::query()->create(['address_id' => $id, 'administration_id' => self::ADMIN_A, 'relation_id' => self::RELATION, 'address_type' => $type, 'address_line_1' => 'Main street 1', 'address_line_2' => null, 'postal_code' => '1234 AB', 'city' => 'Amsterdam', 'country_code' => 'NL', 'active' => $active]);
    }
}
