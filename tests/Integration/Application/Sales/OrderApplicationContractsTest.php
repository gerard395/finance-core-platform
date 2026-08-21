<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Sales;

use App\Application\Sales\AcceptQuotation;
use App\Application\Sales\AddOrderLine;
use App\Application\Sales\AddQuotationLine;
use App\Application\Sales\CancelOrder;
use App\Application\Sales\ConfirmOrder;
use App\Application\Sales\CreateOrder;
use App\Application\Sales\CreateQuotation;
use App\Application\Sales\OrderReadRepository;
use App\Application\Sales\OrderWriteResult;
use App\Application\Sales\RemoveOrderLine;
use App\Application\Sales\SalesNumberSequenceProvisioner;
use App\Application\Sales\SendQuotation;
use App\Application\Sales\UpdateOrder;
use App\Application\Sales\UpdateOrderLine;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Sales\Entities\Order;
use App\Domain\Sales\Entities\OrderLine;
use App\Domain\Sales\Entities\QuotationLine;
use App\Domain\Sales\Enums\OrderStatus;
use App\Domain\Sales\ValueObjects\OrderId;
use App\Domain\Sales\ValueObjects\OrderLineId;
use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Sales\ValueObjects\QuotationLineId;
use App\Domain\Shared\Commerce\ValueObjects\LineDescription;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\AdministrationRecord;
use App\Infrastructure\Persistence\Eloquent\Models\CustomerRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RelationRecord;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class OrderApplicationContractsTest extends TestCase
{
    use RefreshDatabase;

    private const A = 'e1000000-0000-4000-8000-000000000001';

    private const B = 'e2000000-0000-4000-8000-000000000001';

    private const CUSTOMER_A = 'e3000000-0000-4000-8000-000000000001';

    private const CUSTOMER_B = 'e4000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant(self::A, self::CUSTOMER_A, 'A');
        $this->tenant(self::B, self::CUSTOMER_B, 'B');
        $this->app->make(SalesNumberSequenceProvisioner::class)->ensureForAdministration($this->admin(self::A));
        $this->app->make(SalesNumberSequenceProvisioner::class)->ensureForAdministration($this->admin(self::B));
    }

    public function test_create_resolves_active_customer_allocates_number_and_captures_snapshot(): void
    {
        $id = $this->qid(1);
        self::assertSame(OrderWriteResult::Success, $this->create($id, new CustomerId(new Uuid(self::CUSTOMER_A)), [$this->line(1)]));
        $order = $this->read($id);

        self::assertSame('O000001', $order?->number()->value());
        self::assertSame('Customer A', $order?->customerSnapshot()?->displayName()->value());
        self::assertSame('C-A', $order?->customerSnapshot()?->customerNumber()->value());
        self::assertSame(OrderStatus::Draft, $order?->status());
        self::assertSame('20', $order?->total()->amount());
    }

    public function test_inactive_and_cross_tenant_customer_are_typed_and_do_not_allocate(): void
    {
        CustomerRecord::query()->whereKey(self::CUSTOMER_A)->update(['active' => false]);
        self::assertSame(OrderWriteResult::InactiveCustomer, $this->create($this->qid(1), new CustomerId(new Uuid(self::CUSTOMER_A))));
        self::assertSame(OrderWriteResult::CustomerNotFound, $this->create($this->qid(2), new CustomerId(new Uuid(self::CUSTOMER_B))));
        self::assertSame(1, DB::table('sales_number_sequences')->where('administration_id', self::A)->where('sequence_type', 'order')->value('next_value'));
    }

    public function test_persistence_conflict_rolls_number_increment_back(): void
    {
        $id = $this->qid(1);
        self::assertSame(OrderWriteResult::Success, $this->create($id, new CustomerId(new Uuid(self::CUSTOMER_A))));
        $before = DB::table('sales_number_sequences')->where('administration_id', self::A)->where('sequence_type', 'order')->value('next_value');
        self::assertSame(OrderWriteResult::DuplicateIdentity, $this->create($id, new CustomerId(new Uuid(self::CUSTOMER_A))));
        self::assertSame($before, DB::table('sales_number_sequences')->where('administration_id', self::A)->where('sequence_type', 'order')->value('next_value'));
    }

    public function test_draft_header_and_line_use_cases_preserve_immutable_context(): void
    {
        $id = $this->qid(1);
        $this->create($id, new CustomerId(new Uuid(self::CUSTOMER_A)));
        $original = $this->read($id);
        self::assertSame(OrderWriteResult::Success, $this->app->make(UpdateOrder::class)->execute($this->admin(self::A), $id, new DateTimeImmutable('2026-08-22')));
        self::assertSame(OrderWriteResult::Success, $this->app->make(AddOrderLine::class)->execute($this->admin(self::A), $id, $this->line(1)));
        self::assertSame(OrderWriteResult::Success, $this->app->make(UpdateOrderLine::class)->execute($this->admin(self::A), $id, $this->line(1, '3', '5')));
        self::assertSame(OrderWriteResult::InvalidState, $this->app->make(UpdateOrderLine::class)->execute($this->admin(self::A), $id, $this->line(2)));
        self::assertSame(OrderWriteResult::Success, $this->app->make(RemoveOrderLine::class)->execute($this->admin(self::A), $id, $this->line(1)->id()));
        self::assertSame(OrderWriteResult::InvalidState, $this->app->make(RemoveOrderLine::class)->execute($this->admin(self::A), $id, $this->line(1)->id()));

        $changed = $this->read($id);
        self::assertSame($original?->number()->value(), $changed?->number()->value());
        self::assertSame($original?->customerId()->toString(), $changed?->customerId()->toString());
        self::assertSame($original?->currency()->code(), $changed?->currency()->code());
        self::assertSame('2026-08-22', $changed?->orderDate()->format('Y-m-d'));
        self::assertSame([], $changed?->lines());
    }

    public function test_lifecycle_use_cases_follow_domain_and_non_draft_edits_are_rejected(): void
    {
        $confirmed = $this->qid(1);
        $cancelled = $this->qid(2);
        foreach ([$confirmed, $cancelled] as $index => $id) {
            $this->create($id, new CustomerId(new Uuid(self::CUSTOMER_A)), [$this->line($index + 1)]);
        }
        self::assertSame(OrderWriteResult::Success, $this->app->make(ConfirmOrder::class)->execute($this->admin(self::A), $confirmed));
        self::assertSame(OrderWriteResult::InvalidState, $this->app->make(UpdateOrder::class)->execute($this->admin(self::A), $confirmed, new DateTimeImmutable('2026-08-23')));
        self::assertSame(OrderWriteResult::Success, $this->app->make(CancelOrder::class)->execute($this->admin(self::A), $confirmed));
        self::assertSame(OrderWriteResult::Success, $this->app->make(CancelOrder::class)->execute($this->admin(self::A), $cancelled));
        self::assertSame(OrderStatus::Cancelled, $this->read($confirmed)?->status());
        self::assertSame(OrderStatus::Cancelled, $this->read($cancelled)?->status());
        self::assertSame(OrderWriteResult::NotFound, $this->app->make(ConfirmOrder::class)->execute($this->admin(self::B), $confirmed));
    }

    public function test_currency_mismatch_and_empty_send_are_typed_invalid_state(): void
    {
        $id = $this->qid(1);
        $this->create($id, new CustomerId(new Uuid(self::CUSTOMER_A)));
        self::assertSame(OrderWriteResult::InvalidState, $this->app->make(AddOrderLine::class)->execute($this->admin(self::A), $id, $this->line(1, currency: 'USD')));
        self::assertSame(OrderWriteResult::InvalidState, $this->app->make(ConfirmOrder::class)->execute($this->admin(self::A), $id));
    }

    public function test_source_quotation_requires_same_tenant_accepted_and_consistent_context(): void
    {
        $source = new QuotationId(new Uuid('aa000000-0000-4000-8000-000000000001'));
        $this->createQuotation(self::A, self::CUSTOMER_A, $source);
        self::assertSame(OrderWriteResult::SourceQuotationInvalid, $this->app->make(CreateOrder::class)->execute($this->admin(self::A), $this->qid(10), new CustomerId(new Uuid(self::CUSTOMER_A)), new Currency('EUR'), new DateTimeImmutable('2026-08-21'), $source));
        $this->app->make(AddQuotationLine::class)->execute($this->admin(self::A), $source, $this->quotationLine());
        $this->app->make(SendQuotation::class)->execute($this->admin(self::A), $source);
        $this->app->make(AcceptQuotation::class)->execute($this->admin(self::A), $source);
        self::assertSame(OrderWriteResult::Success, $this->app->make(CreateOrder::class)->execute($this->admin(self::A), $this->qid(11), new CustomerId(new Uuid(self::CUSTOMER_A)), new Currency('EUR'), new DateTimeImmutable('2026-08-21'), $source));
        self::assertTrue($source->equals($this->read($this->qid(11))?->sourceQuotationId()));
        self::assertSame(OrderWriteResult::SourceQuotationNotFound, $this->app->make(CreateOrder::class)->execute($this->admin(self::B), $this->qid(12), new CustomerId(new Uuid(self::CUSTOMER_B)), new Currency('EUR'), new DateTimeImmutable('2026-08-21'), $source));
    }

    /** @param list<OrderLine> $lines */
    private function create(OrderId $id, CustomerId $customerId, array $lines = []): OrderWriteResult
    {
        return $this->app->make(CreateOrder::class)->execute($this->admin(self::A), $id, $customerId, new Currency('EUR'), new DateTimeImmutable('2026-08-21'), null, $lines);
    }

    private function read(OrderId $id): ?Order
    {
        return $this->app->make(OrderReadRepository::class)->findForAdministration($this->admin(self::A), $id);
    }

    private function line(int $id, string $quantity = '2', string $amount = '10', string $currency = 'EUR'): OrderLine
    {
        return new OrderLine(new OrderLineId(new Uuid(sprintf('f1000000-0000-4000-8000-%012d', $id))), new LineDescription('Service'), new Quantity($quantity), new Money($amount, new Currency($currency)));
    }

    private function createQuotation(string $administration, string $customer, QuotationId $id): void
    {
        $this->app->make(CreateQuotation::class)->execute($this->admin($administration), $id, new CustomerId(new Uuid($customer)), new Currency('EUR'), new DateTimeImmutable('2026-08-20'), null);
    }

    private function quotationLine(): QuotationLine
    {
        return new QuotationLine(new QuotationLineId(new Uuid('ab000000-0000-4000-8000-000000000001')), new LineDescription('Source line'), new Quantity('1'), new Money('10', new Currency('EUR')));
    }

    private function qid(int $id): OrderId
    {
        return new OrderId(new Uuid(sprintf('f2000000-0000-4000-8000-%012d', $id)));
    }

    private function admin(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }

    private function tenant(string $administration, string $customer, string $suffix): void
    {
        $relation = str_replace('000000-0000-4000', '100000-0000-4000', $customer);
        AdministrationRecord::query()->create(['id' => $administration, 'code' => 'APP-'.$suffix, 'name' => 'Application '.$suffix, 'base_currency' => 'EUR', 'status' => 'active']);
        RelationRecord::query()->create(['id' => $relation, 'administration_id' => $administration, 'code' => 'REL-'.$suffix, 'display_name' => 'Customer '.$suffix, 'active' => true]);
        CustomerRecord::query()->create(['id' => $customer, 'administration_id' => $administration, 'relation_id' => $relation, 'customer_number' => 'C-'.$suffix, 'active' => true]);
    }
}
