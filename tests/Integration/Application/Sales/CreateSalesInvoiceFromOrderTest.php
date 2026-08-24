<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Sales;

use App\Application\Sales\AddSalesInvoiceLine;
use App\Application\Sales\CancelSalesInvoice;
use App\Application\Sales\CreateSalesInvoice;
use App\Application\Sales\CreateSalesInvoiceFromOrder;
use App\Application\Sales\CreateSalesInvoiceFromOrderLineInput;
use App\Application\Sales\CreateSalesInvoiceFromOrderStatus;
use App\Application\Sales\FinalizeSalesInvoice;
use App\Application\Sales\OrderDerivedSalesInvoiceLifecycle;
use App\Application\Sales\OrderInvoiceDraftIdentityGenerator;
use App\Application\Sales\OrderInvoiceDraftRequestReader;
use App\Application\Sales\OrderInvoiceLifecycleIdentityGenerator;
use App\Application\Sales\OrderInvoicingFactAppendResult;
use App\Application\Sales\OrderInvoicingFactStore;
use App\Application\Sales\OrderInvoicingProgressReader;
use App\Application\Sales\OrderInvoicingSource;
use App\Application\Sales\OrderUpdater;
use App\Application\Sales\OrderWriteResult;
use App\Application\Sales\RemoveSalesInvoiceLine;
use App\Application\Sales\SalesInvoiceAddressResolver;
use App\Application\Sales\SalesInvoiceCreator;
use App\Application\Sales\SalesInvoiceLineInput;
use App\Application\Sales\SalesInvoicePostingSource;
use App\Application\Sales\SalesInvoiceReadinessChecker;
use App\Application\Sales\SalesInvoiceReadRepository;
use App\Application\Sales\SalesInvoiceUpdater;
use App\Application\Sales\SalesInvoiceWriteResult;
use App\Application\Sales\SalesNumberAllocator;
use App\Application\Sales\SalesNumberSequenceProvisioner;
use App\Application\Sales\SalesTaxCodeResolver;
use App\Application\Sales\UpdateSalesInvoice;
use App\Application\Sales\UpdateSalesInvoiceLine;
use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Relations\ValueObjects\AddressId;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Sales\Entities\Order;
use App\Domain\Sales\Entities\OrderInvoiceAllocation;
use App\Domain\Sales\Entities\OrderInvoiceDraftRequest;
use App\Domain\Sales\Entities\OrderInvoiceReservation;
use App\Domain\Sales\Entities\OrderInvoiceReservationRelease;
use App\Domain\Sales\Entities\SalesInvoice;
use App\Domain\Sales\ValueObjects\OrderId;
use App\Domain\Sales\ValueObjects\OrderInvoiceDraftRequestId;
use App\Domain\Sales\ValueObjects\OrderLineId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceLineId;
use App\Domain\Shared\Commerce\ValueObjects\LineDescription;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

final class CreateSalesInvoiceFromOrderTest extends TestCase
{
    use RefreshDatabase;

    private const A = '81000000-0000-4000-8000-000000000001';

    private const B = '82000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTenant(self::A, 'A');
        $this->seedTenant(self::B, 'B');
        foreach ([[1, 'confirmed', '10', '5'], [2, 'partially_invoiced', '10', '7'], [3, 'draft', '10', '5'], [4, 'fully_invoiced', '10', '5'], [5, 'cancelled', '10', '5'], [6, 'confirmed', '1', '0.00000001'], [7, 'confirmed', '10', '5']] as [$sequence, $status, $quantity, $price]) {
            $this->seedOrder(self::A, $sequence, $status, $quantity, $price);
        }
        $this->seedOrder(self::B, 1, 'confirmed', '10', '5');
        $this->app->make(SalesNumberSequenceProvisioner::class)->ensureForAdministration($this->admin(self::A));
        $this->app->make(SalesNumberSequenceProvisioner::class)->ensureForAdministration($this->admin(self::B));
    }

    public function test_confirmed_partial_and_multi_line_create_exact_draft_invoice_and_reservations(): void
    {
        DB::table('customers')->where('administration_id', self::A)->update(['active' => false]);
        DB::table('relations')->where('administration_id', self::A)->update(['display_name' => 'Live renamed']);
        $result = $this->create(1, 1, [$this->input(1, '4')]);
        self::assertSame(CreateSalesInvoiceFromOrderStatus::Success, $result->status());
        $invoice = $this->invoice($result->salesInvoiceId());
        self::assertSame('F000001', $invoice?->number()->value());
        self::assertSame('draft', $invoice?->status()->value);
        self::assertTrue($this->orderId(self::A, 1)->equals($invoice?->sourceOrderId()));
        self::assertSame('Customer A', $invoice?->customerSnapshot()?->displayName()->value());
        self::assertSame('EUR', $invoice?->currency()->code());
        self::assertSame('Invoice A', $invoice?->invoiceAddressSnapshot()?->addressLine()->value());
        self::assertSame('4', $invoice?->lines()[0]->quantity()->value());
        self::assertSame('5', $invoice?->lines()[0]->unitPrice()->amount());
        self::assertSame('Service A 1', $invoice?->lines()[0]->description()->value());
        self::assertSame('21', $invoice?->lines()[0]->taxSnapshot()?->taxRate()->value());
        self::assertNotSame($this->orderLineId(self::A, 1, 1)->toString(), $invoice?->lines()[0]->id()->toString());
        self::assertSame('20', $invoice?->total()->amount());
        $readiness = $this->app->make(SalesInvoiceReadinessChecker::class)->check($invoice);
        self::assertSame('20', $readiness->netTotal()?->amount());
        self::assertSame('4.2', $readiness->taxTotal()?->amount());
        self::assertSame('24.2', $readiness->grossTotal()?->amount());
        self::assertSame('confirmed', DB::table('orders')->where('id', $this->orderId(self::A, 1)->toString())->value('status'));
        $progress = $this->app->make(OrderInvoicingProgressReader::class)->progress($this->admin(self::A), $this->orderId(self::A, 1));
        self::assertSame('4', $progress?->lines()[0]->reserved()->value());
        self::assertSame('0', $progress?->lines()[0]->allocated()->value());

        $this->seedSecondLine(self::A, 2, '5', '3');
        $multi = $this->create(2, 2, [$this->input(2, '6'), $this->input(2, '5', 2)]);
        self::assertSame(CreateSalesInvoiceFromOrderStatus::Success, $multi->status());
        self::assertCount(2, $this->invoice($multi->salesInvoiceId())?->lines());
        self::assertSame('partially_invoiced', DB::table('orders')->where('id', $this->orderId(self::A, 2)->toString())->value('status'));

        $full = $this->create(7, 3, [$this->input(7, '10')]);
        self::assertSame(CreateSalesInvoiceFromOrderStatus::Success, $full->status());
        self::assertSame('10', $this->invoice($full->salesInvoiceId())?->lines()[0]->quantity()->value());
    }

    public function test_eligibility_quantity_and_tenant_failures_do_not_allocate(): void
    {
        foreach ([3, 4, 5] as $request) {
            self::assertSame(CreateSalesInvoiceFromOrderStatus::InvalidOrderState, $this->create($request, $request, [$this->input($request, '1')])->status());
        }
        self::assertSame(CreateSalesInvoiceFromOrderStatus::NothingToInvoice, $this->create(1, 20, [])->status());
        self::assertSame(CreateSalesInvoiceFromOrderStatus::QuantityExceedsRemaining, $this->create(1, 21, [$this->input(1, '11')])->status());
        $foreignLine = new CreateSalesInvoiceFromOrderLineInput($this->orderLineId(self::B, 1, 1), new Quantity('1'), $this->taxId(self::A, 1));
        self::assertSame(CreateSalesInvoiceFromOrderStatus::NotFound, $this->create(1, 22, [$foreignLine])->status());
        $crossTenant = $this->app->make(CreateSalesInvoiceFromOrder::class)->execute($this->admin(self::A), $this->orderId(self::B, 1), $this->requestId(23), $this->addressId(self::A), new DateTimeImmutable('2026-08-24'), new DateTimeImmutable('2026-09-24'), [$foreignLine]);
        self::assertSame(CreateSalesInvoiceFromOrderStatus::NotFound, $crossTenant->status());
        self::assertSame(1, DB::table('sales_number_sequences')->where('administration_id', self::A)->where('sequence_type', 'sales_invoice')->value('next_value'));
    }

    public function test_replay_returns_existing_invoice_and_conflicting_payload_is_rejected(): void
    {
        $first = $this->create(1, 30, [$this->input(1, '4')]);
        $replay = $this->create(1, 30, [$this->input(1, '4')]);
        self::assertSame(CreateSalesInvoiceFromOrderStatus::AlreadyCreated, $replay->status());
        self::assertTrue($first->salesInvoiceId()?->equals($replay->salesInvoiceId()));
        self::assertSame(CreateSalesInvoiceFromOrderStatus::RequestConflict, $this->create(1, 30, [$this->input(1, '3')])->status());
        self::assertSame(1, DB::table('sales_invoices')->where('source_order_id', $this->orderId(self::A, 1)->toString())->count());
        self::assertSame(1, DB::table('order_invoice_reservations')->where('draft_request_id', $this->requestId(30)->toString())->count());
        self::assertSame(2, DB::table('sales_number_sequences')->where('administration_id', self::A)->where('sequence_type', 'sales_invoice')->value('next_value'));
    }

    public function test_address_tax_sequence_and_persistence_failures_write_nothing_and_roll_back_number(): void
    {
        DB::table('relation_addresses')->where('address_id', $this->addressId(self::A)->toString())->update(['active' => false]);
        self::assertSame(CreateSalesInvoiceFromOrderStatus::MissingInvoiceAddress, $this->create(1, 40, [$this->input(1, '1')])->status());
        DB::table('relation_addresses')->where('address_id', $this->addressId(self::A)->toString())->update(['active' => true]);
        $crossTenantAddress = $this->app->make(CreateSalesInvoiceFromOrder::class)->execute($this->admin(self::A), $this->orderId(self::A, 1), $this->requestId(47), $this->addressId(self::B), new DateTimeImmutable('2026-08-24'), new DateTimeImmutable('2026-09-24'), [$this->input(1, '1')]);
        self::assertSame(CreateSalesInvoiceFromOrderStatus::MissingInvoiceAddress, $crossTenantAddress->status());
        self::assertSame(CreateSalesInvoiceFromOrderStatus::TaxCodeInactive, $this->create(1, 41, [$this->input(1, '1', 1, 2)])->status());
        self::assertSame(CreateSalesInvoiceFromOrderStatus::TaxCodeWrongDirection, $this->create(1, 42, [$this->input(1, '1', 1, 3)])->status());
        $foreignTax = new CreateSalesInvoiceFromOrderLineInput($this->orderLineId(self::A, 1, 1), new Quantity('1'), $this->taxId(self::B, 1));
        self::assertSame(CreateSalesInvoiceFromOrderStatus::TaxCodeNotFound, $this->create(1, 43, [$foreignTax])->status());
        $beforeTaxFailure = DB::table('sales_number_sequences')->where('administration_id', self::A)->where('sequence_type', 'sales_invoice')->value('next_value');
        self::assertSame(CreateSalesInvoiceFromOrderStatus::TaxCalculationFailed, $this->create(6, 44, [$this->input(6, '1')])->status());
        self::assertSame($beforeTaxFailure, DB::table('sales_number_sequences')->where('administration_id', self::A)->where('sequence_type', 'sales_invoice')->value('next_value'));
        DB::table('sales_number_sequences')->where('administration_id', self::A)->where('sequence_type', 'sales_invoice')->update(['active' => false]);
        self::assertSame(CreateSalesInvoiceFromOrderStatus::SequenceInactive, $this->create(1, 45, [$this->input(1, '1')])->status());
        DB::table('sales_number_sequences')->where('administration_id', self::A)->where('sequence_type', 'sales_invoice')->update(['active' => true]);
        DB::table('sales_number_sequences')->where('administration_id', self::A)->where('sequence_type', 'sales_invoice')->delete();
        self::assertSame(CreateSalesInvoiceFromOrderStatus::SequenceMissing, $this->create(1, 48, [$this->input(1, '1')])->status());
        $this->app->make(SalesNumberSequenceProvisioner::class)->ensureForAdministration($this->admin(self::A));

        $before = DB::table('sales_number_sequences')->where('administration_id', self::A)->where('sequence_type', 'sales_invoice')->value('next_value');
        $useCase = $this->useCase(new FailingOrderInvoiceCreator);
        $failed = $useCase->execute($this->admin(self::A), $this->orderId(self::A, 1), $this->requestId(46), $this->addressId(self::A), new DateTimeImmutable('2026-08-24'), new DateTimeImmutable('2026-09-24'), [$this->input(1, '1')]);
        self::assertSame(CreateSalesInvoiceFromOrderStatus::PersistenceConflict, $failed->status());
        self::assertSame($before, DB::table('sales_number_sequences')->where('administration_id', self::A)->where('sequence_type', 'sales_invoice')->value('next_value'));
        self::assertSame(0, DB::table('order_invoice_draft_requests')->count());
        self::assertSame(0, DB::table('order_invoice_reservations')->count());
    }

    public function test_order_derived_lines_are_locked_but_header_and_direct_invoice_lifecycle_remain_supported(): void
    {
        $created = $this->create(1, 50, [$this->input(1, '2')]);
        $invoice = $this->invoice($created->salesInvoiceId());
        self::assertNotNull($invoice);
        $line = $invoice->lines()[0];
        $mutation = new SalesInvoiceLineInput($line->id(), new LineDescription('Attack'), new Quantity('1'), new Money('1', new Currency('EUR')), $this->taxId(self::A, 1));
        self::assertSame(SalesInvoiceWriteResult::InvalidState, $this->app->make(AddSalesInvoiceLine::class)->execute($this->admin(self::A), $invoice->id(), $mutation));
        self::assertSame(SalesInvoiceWriteResult::InvalidState, $this->app->make(UpdateSalesInvoiceLine::class)->execute($this->admin(self::A), $invoice->id(), $mutation));
        self::assertSame(SalesInvoiceWriteResult::InvalidState, $this->app->make(RemoveSalesInvoiceLine::class)->execute($this->admin(self::A), $invoice->id(), $line->id()));
        self::assertSame(SalesInvoiceWriteResult::Success, $this->app->make(UpdateSalesInvoice::class)->execute($this->admin(self::A), $invoice->id(), new DateTimeImmutable('2026-08-25'), new DateTimeImmutable('2026-09-25')));

        $directId = new SalesInvoiceId(new Uuid('8e000000-0000-4000-8000-000000000099'));
        self::assertSame(SalesInvoiceWriteResult::Success, $this->app->make(CreateSalesInvoice::class)->execute($this->admin(self::A), $directId, new CustomerId(new Uuid($this->customer(self::A))), $this->addressId(self::A), new DateTimeImmutable('2026-08-24'), new DateTimeImmutable('2026-09-24')));
        self::assertSame(SalesInvoiceWriteResult::Success, $this->app->make(AddSalesInvoiceLine::class)->execute($this->admin(self::A), $directId, new SalesInvoiceLineInput(new SalesInvoiceLineId(new Uuid('8f000000-0000-4000-8000-000000000099')), new LineDescription('Direct'), new Quantity('1'), new Money('5', new Currency('EUR')), $this->taxId(self::A, 1))));
        self::assertSame(SalesInvoiceWriteResult::Success, $this->app->make(FinalizeSalesInvoice::class)->execute($this->admin(self::A), $directId));
        self::assertSame(SalesInvoiceWriteResult::Success, $this->app->make(CancelSalesInvoice::class)->execute($this->admin(self::A), $directId));
        self::assertNull($this->invoice($directId)?->sourceOrderId());
        self::assertSame(0, DB::table('order_invoice_draft_requests')->where('sales_invoice_id', $directId->toString())->count());
    }

    public function test_finalize_converts_exact_reservations_and_derives_partial_and_full_status_per_line(): void
    {
        $partial = $this->create(1, 70, [$this->input(1, '4')]);
        self::assertSame(SalesInvoiceWriteResult::Success, $this->app->make(FinalizeSalesInvoice::class)->execute($this->admin(self::A), $partial->salesInvoiceId()));
        self::assertSame('finalized', $this->invoice($partial->salesInvoiceId())?->status()->value);
        self::assertSame('partially_invoiced', DB::table('orders')->where('id', $this->orderId(self::A, 1)->toString())->value('status'));
        $progress = $this->app->make(OrderInvoicingProgressReader::class)->progress($this->admin(self::A), $this->orderId(self::A, 1));
        self::assertSame('0', $progress?->lines()[0]->reserved()->value());
        self::assertSame('4', $progress?->lines()[0]->allocated()->value());
        self::assertSame('6', $progress?->lines()[0]->available()->value());
        self::assertSame(1, DB::table('order_invoice_allocations')->where('sales_invoice_id', $partial->salesInvoiceId()?->toString())->count());
        self::assertSame(SalesInvoiceWriteResult::Success, $this->app->make(FinalizeSalesInvoice::class)->execute($this->admin(self::A), $partial->salesInvoiceId()));
        self::assertSame(1, DB::table('order_invoice_allocations')->where('sales_invoice_id', $partial->salesInvoiceId()?->toString())->count());

        $full = $this->create(7, 71, [$this->input(7, '10')]);
        self::assertSame(SalesInvoiceWriteResult::Success, $this->app->make(FinalizeSalesInvoice::class)->execute($this->admin(self::A), $full->salesInvoiceId()));
        self::assertSame('fully_invoiced', DB::table('orders')->where('id', $this->orderId(self::A, 7)->toString())->value('status'));

        $this->seedSecondLine(self::A, 2, '5', '3');
        $multiPartial = $this->create(2, 72, [$this->input(2, '10'), $this->input(2, '3', 2)]);
        self::assertSame(SalesInvoiceWriteResult::Success, $this->app->make(FinalizeSalesInvoice::class)->execute($this->admin(self::A), $multiPartial->salesInvoiceId()));
        self::assertSame('partially_invoiced', DB::table('orders')->where('id', $this->orderId(self::A, 2)->toString())->value('status'));
        $multiFull = $this->create(2, 73, [$this->input(2, '2', 2)]);
        self::assertSame(SalesInvoiceWriteResult::Success, $this->app->make(FinalizeSalesInvoice::class)->execute($this->admin(self::A), $multiFull->salesInvoiceId()));
        self::assertSame('fully_invoiced', DB::table('orders')->where('id', $this->orderId(self::A, 2)->toString())->value('status'));
    }

    public function test_multiple_drafts_finalize_and_cancel_only_their_own_reservations(): void
    {
        $a = $this->create(1, 80, [$this->input(1, '6')]);
        $b = $this->create(1, 81, [$this->input(1, '4')]);
        self::assertSame(SalesInvoiceWriteResult::Success, $this->app->make(FinalizeSalesInvoice::class)->execute($this->admin(self::A), $a->salesInvoiceId()));
        $progress = $this->app->make(OrderInvoicingProgressReader::class)->progress($this->admin(self::A), $this->orderId(self::A, 1));
        self::assertSame('4', $progress?->lines()[0]->reserved()->value());
        self::assertSame('6', $progress?->lines()[0]->allocated()->value());
        self::assertSame('0', $progress?->lines()[0]->available()->value());
        self::assertSame(SalesInvoiceWriteResult::Success, $this->app->make(FinalizeSalesInvoice::class)->execute($this->admin(self::A), $b->salesInvoiceId()));
        self::assertSame('fully_invoiced', DB::table('orders')->where('id', $this->orderId(self::A, 1)->toString())->value('status'));

        $cancelA = $this->create(7, 82, [$this->input(7, '6')]);
        $cancelB = $this->create(7, 83, [$this->input(7, '4')]);
        self::assertSame(SalesInvoiceWriteResult::Success, $this->app->make(CancelSalesInvoice::class)->execute($this->admin(self::A), $cancelA->salesInvoiceId()));
        $cancelProgress = $this->app->make(OrderInvoicingProgressReader::class)->progress($this->admin(self::A), $this->orderId(self::A, 7));
        self::assertSame('4', $cancelProgress?->lines()[0]->reserved()->value());
        self::assertSame('0', $cancelProgress?->lines()[0]->allocated()->value());
        self::assertSame('6', $cancelProgress?->lines()[0]->available()->value());
        self::assertSame('confirmed', DB::table('orders')->where('id', $this->orderId(self::A, 7)->toString())->value('status'));
        self::assertSame(1, DB::table('order_invoice_reservation_releases')->count());
        self::assertSame(SalesInvoiceWriteResult::Success, $this->app->make(CancelSalesInvoice::class)->execute($this->admin(self::A), $cancelA->salesInvoiceId()));
        self::assertSame(1, DB::table('order_invoice_reservation_releases')->count());

        self::assertSame(SalesInvoiceWriteResult::Success, $this->app->make(FinalizeSalesInvoice::class)->execute($this->admin(self::A), $cancelB->salesInvoiceId()));
        self::assertSame(SalesInvoiceWriteResult::Success, $this->app->make(CancelSalesInvoice::class)->execute($this->admin(self::A), $cancelB->salesInvoiceId()));
        self::assertSame('partially_invoiced', DB::table('orders')->where('id', $this->orderId(self::A, 7)->toString())->value('status'));
        self::assertSame(1, DB::table('order_invoice_allocations')->where('sales_invoice_id', $cancelB->salesInvoiceId()?->toString())->count());
    }

    public function test_finalize_rejects_inconsistent_or_cross_tenant_state_without_writes(): void
    {
        $created = $this->create(1, 90, [$this->input(1, '4')]);
        DB::table('order_invoice_reservations')->where('sales_invoice_id', $created->salesInvoiceId()?->toString())->delete();
        self::assertSame(SalesInvoiceWriteResult::ReservationStateInconsistent, $this->app->make(FinalizeSalesInvoice::class)->execute($this->admin(self::A), $created->salesInvoiceId()));
        self::assertSame('draft', $this->invoice($created->salesInvoiceId())?->status()->value);
        self::assertSame('confirmed', DB::table('orders')->where('id', $this->orderId(self::A, 1)->toString())->value('status'));
        self::assertSame(0, DB::table('order_invoice_allocations')->count());
        self::assertSame(SalesInvoiceWriteResult::NotFound, $this->app->make(FinalizeSalesInvoice::class)->execute($this->admin(self::B), $created->salesInvoiceId()));
    }

    public function test_finalize_and_cancel_failures_roll_back_facts_statuses_and_quantity_ledger(): void
    {
        foreach (range(8, 12) as $order) {
            $this->seedOrder(self::A, $order, 'confirmed', '10', '5');
        }

        $allocationFailure = $this->create(8, 108, [$this->input(8, '4')]);
        $failingFacts = new SelectivelyFailingOrderInvoicingFactStore($this->app->make(OrderInvoicingFactStore::class), true, false);
        self::assertSame(SalesInvoiceWriteResult::PersistenceConflict, $this->lifecycle($failingFacts)->finalize($this->admin(self::A), $allocationFailure->salesInvoiceId(), $this->orderId(self::A, 8)));
        $this->assertRolledBackDraft(8, $allocationFailure->salesInvoiceId(), '4');

        $orderUpdateFailure = $this->create(9, 109, [$this->input(9, '4')]);
        self::assertSame(SalesInvoiceWriteResult::PersistenceConflict, $this->lifecycle(null, new FailingOrderUpdater)->finalize($this->admin(self::A), $orderUpdateFailure->salesInvoiceId(), $this->orderId(self::A, 9)));
        $this->assertRolledBackDraft(9, $orderUpdateFailure->salesInvoiceId(), '4');

        $invoiceUpdateFailure = $this->create(10, 110, [$this->input(10, '4')]);
        self::assertSame(SalesInvoiceWriteResult::PersistenceConflict, $this->lifecycle(null, null, new FailingSalesInvoiceUpdater)->finalize($this->admin(self::A), $invoiceUpdateFailure->salesInvoiceId(), $this->orderId(self::A, 10)));
        $this->assertRolledBackDraft(10, $invoiceUpdateFailure->salesInvoiceId(), '4');

        $releaseFailure = $this->create(11, 111, [$this->input(11, '4')]);
        $failingRelease = new SelectivelyFailingOrderInvoicingFactStore($this->app->make(OrderInvoicingFactStore::class), false, true);
        self::assertSame(SalesInvoiceWriteResult::PersistenceConflict, $this->lifecycle($failingRelease)->cancel($this->admin(self::A), $releaseFailure->salesInvoiceId(), $this->orderId(self::A, 11)));
        $this->assertRolledBackDraft(11, $releaseFailure->salesInvoiceId(), '4');

        $cancelUpdateFailure = $this->create(12, 112, [$this->input(12, '4')]);
        self::assertSame(SalesInvoiceWriteResult::PersistenceConflict, $this->lifecycle(null, null, new FailingSalesInvoiceUpdater)->cancel($this->admin(self::A), $cancelUpdateFailure->salesInvoiceId(), $this->orderId(self::A, 12)));
        $this->assertRolledBackDraft(12, $cancelUpdateFailure->salesInvoiceId(), '4');
        self::assertSame(0, DB::table('order_invoice_reservation_releases')->count());
        self::assertSame(0, DB::table('order_invoice_allocations')->count());
    }

    public function test_real_mysql_duplicate_finalize_is_idempotent_and_writes_one_allocation(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required.');
        }
        $created = $this->create(1, 100, [$this->input(1, '10')]);
        DB::commit();
        $files = [tempnam(sys_get_temp_dir(), 'finalize-invoice-'), tempnam(sys_get_temp_dir(), 'finalize-invoice-')];
        $children = [];
        foreach ($files as $file) {
            self::assertIsString($file);
            $pid = pcntl_fork();
            self::assertNotSame(-1, $pid);
            if ($pid === 0) {
                try {
                    DB::purge();
                    $result = $this->app->make(FinalizeSalesInvoice::class)->execute($this->admin(self::A), $created->salesInvoiceId());
                    file_put_contents($file, $result->name);
                    exit(0);
                } catch (Throwable $exception) {
                    file_put_contents($file, 'ERROR:'.$exception->getMessage());
                    exit(1);
                }
            }
            $children[] = $pid;
        }
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            self::assertTrue(pcntl_wifexited($status));
            self::assertSame(0, pcntl_wexitstatus($status));
        }
        self::assertSame(['Success', 'Success'], array_map(static fn (string $file): string => trim((string) file_get_contents($file)), $files));
        foreach ($files as $file) {
            unlink($file);
        }
        self::assertSame(1, DB::table('order_invoice_allocations')->count());
        self::assertSame(0, DB::table('order_invoice_reservation_releases')->count());
        self::assertSame('finalized', DB::table('sales_invoices')->where('id', $created->salesInvoiceId()?->toString())->value('status'));
        self::assertSame('fully_invoiced', DB::table('orders')->where('id', $this->orderId(self::A, 1)->toString())->value('status'));
        $this->removeCommittedFixtures();
        DB::beginTransaction();
    }

    public function test_real_mysql_finalize_and_create_share_order_lock_and_never_over_invoice(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required.');
        }
        $created = $this->create(1, 120, [$this->input(1, '6')]);
        DB::commit();
        $files = [tempnam(sys_get_temp_dir(), 'finalize-create-'), tempnam(sys_get_temp_dir(), 'finalize-create-')];
        $children = [];
        foreach ($files as $index => $file) {
            self::assertIsString($file);
            $pid = pcntl_fork();
            self::assertNotSame(-1, $pid);
            if ($pid === 0) {
                try {
                    DB::purge();
                    $result = $index === 0
                        ? $this->app->make(FinalizeSalesInvoice::class)->execute($this->admin(self::A), $created->salesInvoiceId())->name
                        : $this->create(1, 121, [$this->input(1, '5')])->status()->name;
                    file_put_contents($file, $result);
                    exit(0);
                } catch (Throwable $exception) {
                    file_put_contents($file, 'ERROR:'.$exception->getMessage());
                    exit(1);
                }
            }
            $children[] = $pid;
        }
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            self::assertTrue(pcntl_wifexited($status));
            self::assertSame(0, pcntl_wexitstatus($status));
        }
        $results = array_map(static fn (string $file): string => trim((string) file_get_contents($file)), $files);
        foreach ($files as $file) {
            unlink($file);
        }
        sort($results);
        self::assertSame(['QuantityExceedsRemaining', 'Success'], $results);
        self::assertSame(1, DB::table('sales_invoices')->where('source_order_id', $this->orderId(self::A, 1)->toString())->count());
        self::assertSame(1, DB::table('order_invoice_allocations')->count());
        $progress = $this->app->make(OrderInvoicingProgressReader::class)->progress($this->admin(self::A), $this->orderId(self::A, 1));
        self::assertSame('0', $progress?->lines()[0]->reserved()->value());
        self::assertSame('6', $progress?->lines()[0]->allocated()->value());
        self::assertSame('4', $progress?->lines()[0]->available()->value());
        $this->removeCommittedFixtures();
        DB::beginTransaction();
    }

    public function test_real_mysql_concurrent_create_never_over_reserves_or_consumes_failed_number(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required.');
        }
        DB::commit();
        $files = [tempnam(sys_get_temp_dir(), 'create-invoice-'), tempnam(sys_get_temp_dir(), 'create-invoice-')];
        $children = [];
        foreach ($files as $index => $file) {
            self::assertIsString($file);
            $pid = pcntl_fork();
            self::assertNotSame(-1, $pid);
            if ($pid === 0) {
                try {
                    DB::purge();
                    $result = $this->create(1, 60 + $index, [$this->input(1, '6')]);
                    file_put_contents($file, $result->status()->name);
                    exit(0);
                } catch (Throwable $exception) {
                    file_put_contents($file, 'ERROR:'.$exception->getMessage());
                    exit(1);
                }
            }
            $children[] = $pid;
        }
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            self::assertTrue(pcntl_wifexited($status));
            self::assertSame(0, pcntl_wexitstatus($status));
        }
        $results = array_map(static fn (string $file): string => trim((string) file_get_contents($file)), $files);
        foreach ($files as $file) {
            unlink($file);
        }
        sort($results);
        self::assertSame(['QuantityExceedsRemaining', 'Success'], $results);
        self::assertSame(1, DB::table('sales_invoices')->where('source_order_id', $this->orderId(self::A, 1)->toString())->count());
        self::assertSame('6', (string) DB::table('order_invoice_reservations')->sum('quantity'));
        self::assertSame(2, DB::table('sales_number_sequences')->where('administration_id', self::A)->where('sequence_type', 'sales_invoice')->value('next_value'));
        $this->removeCommittedFixtures();
        DB::beginTransaction();
    }

    private function create(int $order, int $request, array $lines)
    {
        return $this->app->make(CreateSalesInvoiceFromOrder::class)->execute($this->admin(self::A), $this->orderId(self::A, $order), $this->requestId($request), $this->addressId(self::A), new DateTimeImmutable('2026-08-24'), new DateTimeImmutable('2026-09-24'), $lines);
    }

    private function input(int $order, string $quantity, int $line = 1, int $tax = 1): CreateSalesInvoiceFromOrderLineInput
    {
        return new CreateSalesInvoiceFromOrderLineInput($this->orderLineId(self::A, $order, $line), new Quantity($quantity), $this->taxId(self::A, $tax));
    }

    private function invoice(?SalesInvoiceId $id): ?SalesInvoice
    {
        return $id === null ? null : $this->app->make(SalesInvoiceReadRepository::class)->findForAdministration($this->admin(self::A), $id);
    }

    private function useCase(SalesInvoiceCreator $creator): CreateSalesInvoiceFromOrder
    {
        return new CreateSalesInvoiceFromOrder($this->app->make(TransactionManager::class), $this->app->make(OrderInvoicingSource::class), $this->app->make(OrderInvoiceDraftRequestReader::class), $this->app->make(OrderInvoicingProgressReader::class), $this->app->make(SalesInvoiceAddressResolver::class), $this->app->make(SalesTaxCodeResolver::class), $this->app->make(SalesInvoiceReadinessChecker::class), $this->app->make(SalesNumberAllocator::class), $this->app->make(OrderInvoiceDraftIdentityGenerator::class), $creator, $this->app->make(OrderInvoicingFactStore::class), $this->app->make(SalesInvoiceReadRepository::class));
    }

    private function lifecycle(?OrderInvoicingFactStore $facts = null, ?OrderUpdater $orders = null, ?SalesInvoiceUpdater $invoices = null): OrderDerivedSalesInvoiceLifecycle
    {
        return new OrderDerivedSalesInvoiceLifecycle(
            $this->app->make(TransactionManager::class), $this->app->make(OrderInvoicingSource::class), $this->app->make(SalesInvoicePostingSource::class),
            $this->app->make(OrderInvoiceDraftRequestReader::class), $this->app->make(OrderInvoicingProgressReader::class), $facts ?? $this->app->make(OrderInvoicingFactStore::class),
            $this->app->make(OrderInvoiceLifecycleIdentityGenerator::class), $orders ?? $this->app->make(OrderUpdater::class), $invoices ?? $this->app->make(SalesInvoiceUpdater::class),
            $this->app->make(SalesInvoiceReadinessChecker::class),
        );
    }

    private function assertRolledBackDraft(int $order, ?SalesInvoiceId $invoiceId, string $reserved): void
    {
        self::assertSame('draft', $this->invoice($invoiceId)?->status()->value);
        self::assertSame('confirmed', DB::table('orders')->where('id', $this->orderId(self::A, $order)->toString())->value('status'));
        $progress = $this->app->make(OrderInvoicingProgressReader::class)->progress($this->admin(self::A), $this->orderId(self::A, $order));
        self::assertSame($reserved, $progress?->lines()[0]->reserved()->value());
        self::assertSame('0', $progress?->lines()[0]->allocated()->value());
        self::assertSame((string) (10 - (int) $reserved), $progress?->lines()[0]->available()->value());
    }

    private function seedTenant(string $admin, string $suffix): void
    {
        $now = now();
        DB::table('administrations')->insert(['id' => $admin, 'code' => 'FROM-'.$suffix, 'name' => 'From '.$suffix, 'base_currency' => 'EUR', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('relations')->insert(['id' => $this->relation($admin), 'administration_id' => $admin, 'code' => 'R-'.$suffix, 'display_name' => 'Customer '.$suffix, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('customers')->insert(['id' => $this->customer($admin), 'administration_id' => $admin, 'relation_id' => $this->relation($admin), 'customer_number' => 'C-'.$suffix, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('relation_addresses')->insert(['address_id' => $this->addressId($admin)->toString(), 'administration_id' => $admin, 'relation_id' => $this->relation($admin), 'address_type' => 'invoice', 'address_line_1' => 'Invoice '.$suffix, 'address_line_2' => null, 'postal_code' => '1234 AB', 'city' => 'City', 'country_code' => 'NL', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        foreach ([[1, '21', 'output', 'active'], [2, '9', 'output', 'inactive'], [3, '21', 'input', 'active']] as [$sequence, $rate, $direction, $status]) {
            DB::table('tax_codes')->insert(['id' => $this->taxId($admin, $sequence)->toString(), 'administration_id' => $admin, 'code' => 'T'.$suffix.$sequence, 'name' => 'Tax', 'rate' => $rate, 'direction' => $direction, 'status' => $status, 'treatment' => 'domestic_standard', 'vat_return_classification' => 'domestic_standard', 'icp_classification' => 'none', 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    private function seedOrder(string $admin, int $sequence, string $status, string $quantity, string $price): void
    {
        $now = now();
        $suffix = $admin === self::A ? 'A' : 'B';
        DB::table('orders')->insert(['id' => $this->orderId($admin, $sequence)->toString(), 'administration_id' => $admin, 'order_number' => 'O'.$suffix.$sequence, 'customer_id' => $this->customer($admin), 'customer_relation_id_snapshot' => $this->relation($admin), 'customer_number_snapshot' => 'C-'.$suffix, 'customer_name_snapshot' => 'Customer '.$suffix, 'source_quotation_id' => null, 'currency' => 'EUR', 'order_date' => '2026-08-24', 'status' => $status, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('order_lines')->insert(['id' => $this->orderLineId($admin, $sequence, 1)->toString(), 'administration_id' => $admin, 'order_id' => $this->orderId($admin, $sequence)->toString(), 'description' => 'Service '.$suffix.' '.$sequence, 'quantity' => $quantity, 'unit_price_amount' => $price, 'currency' => 'EUR', 'created_at' => $now, 'updated_at' => $now]);
    }

    private function seedSecondLine(string $admin, int $order, string $quantity, string $price): void
    {
        DB::table('order_lines')->insert(['id' => $this->orderLineId($admin, $order, 2)->toString(), 'administration_id' => $admin, 'order_id' => $this->orderId($admin, $order)->toString(), 'description' => 'Second', 'quantity' => $quantity, 'unit_price_amount' => $price, 'currency' => 'EUR', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function admin(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }

    private function orderId(string $admin, int $sequence): OrderId
    {
        return new OrderId(new Uuid($this->uuid($admin, 100 + $sequence)));
    }

    private function orderLineId(string $admin, int $order, int $line): OrderLineId
    {
        return new OrderLineId(new Uuid($this->uuid($admin, 1000 + ($order * 10) + $line)));
    }

    private function addressId(string $admin): AddressId
    {
        return new AddressId(new Uuid($this->uuid($admin, 20)));
    }

    private function taxId(string $admin, int $sequence): TaxCodeId
    {
        return new TaxCodeId(new Uuid($this->uuid($admin, 30 + $sequence)));
    }

    private function requestId(int $sequence): OrderInvoiceDraftRequestId
    {
        return new OrderInvoiceDraftRequestId(new Uuid(sprintf('8d000000-0000-4000-8000-%012d', $sequence)));
    }

    private function relation(string $admin): string
    {
        return $this->uuid($admin, 2);
    }

    private function customer(string $admin): string
    {
        return $this->uuid($admin, 3);
    }

    private function uuid(string $admin, int $suffix): string
    {
        return substr($admin, 0, 24).sprintf('%012d', $suffix);
    }

    private function removeCommittedFixtures(): void
    {
        foreach (['order_invoice_allocations', 'order_invoice_reservation_releases', 'order_invoice_reservations', 'order_invoice_draft_requests', 'sales_invoice_lines', 'sales_invoices', 'order_lines', 'orders', 'sales_number_sequences', 'tax_codes', 'relation_addresses', 'customers', 'relations', 'administrations'] as $table) {
            DB::table($table)->delete();
        }
    }
}

final class FailingOrderInvoiceCreator implements SalesInvoiceCreator
{
    public function create(AdministrationId $administrationId, SalesInvoice $invoice): SalesInvoiceWriteResult
    {
        return SalesInvoiceWriteResult::DuplicateIdentity;
    }
}

final readonly class SelectivelyFailingOrderInvoicingFactStore implements OrderInvoicingFactStore
{
    public function __construct(private OrderInvoicingFactStore $delegate, private bool $failAllocation, private bool $failRelease) {}

    public function appendDraftRequest(OrderInvoiceDraftRequest $request): OrderInvoicingFactAppendResult
    {
        return $this->delegate->appendDraftRequest($request);
    }

    public function appendReservation(OrderInvoiceReservation $reservation): OrderInvoicingFactAppendResult
    {
        return $this->delegate->appendReservation($reservation);
    }

    public function appendRelease(OrderInvoiceReservationRelease $release): OrderInvoicingFactAppendResult
    {
        return $this->failRelease ? OrderInvoicingFactAppendResult::PersistenceConflict : $this->delegate->appendRelease($release);
    }

    public function appendAllocation(OrderInvoiceAllocation $allocation): OrderInvoicingFactAppendResult
    {
        return $this->failAllocation ? OrderInvoicingFactAppendResult::PersistenceConflict : $this->delegate->appendAllocation($allocation);
    }
}

final class FailingOrderUpdater implements OrderUpdater
{
    public function update(AdministrationId $administrationId, Order $order): OrderWriteResult
    {
        return OrderWriteResult::InvalidState;
    }
}

final class FailingSalesInvoiceUpdater implements SalesInvoiceUpdater
{
    public function update(AdministrationId $administrationId, SalesInvoice $invoice): SalesInvoiceWriteResult
    {
        return SalesInvoiceWriteResult::InvalidState;
    }
}
