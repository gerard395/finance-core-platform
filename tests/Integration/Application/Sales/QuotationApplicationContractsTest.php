<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Sales;

use App\Application\Sales\AcceptQuotation;
use App\Application\Sales\AddQuotationLine;
use App\Application\Sales\CreateQuotation;
use App\Application\Sales\ExpireQuotation;
use App\Application\Sales\QuotationReadRepository;
use App\Application\Sales\QuotationWriteResult;
use App\Application\Sales\RejectQuotation;
use App\Application\Sales\RemoveQuotationLine;
use App\Application\Sales\SalesNumberSequenceProvisioner;
use App\Application\Sales\SendQuotation;
use App\Application\Sales\UpdateQuotation;
use App\Application\Sales\UpdateQuotationLine;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Sales\Entities\Quotation;
use App\Domain\Sales\Entities\QuotationLine;
use App\Domain\Sales\Enums\QuotationStatus;
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

final class QuotationApplicationContractsTest extends TestCase
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
    }

    public function test_create_resolves_active_customer_allocates_number_and_captures_snapshot(): void
    {
        $id = $this->qid(1);
        self::assertSame(QuotationWriteResult::Success, $this->create($id, new CustomerId(new Uuid(self::CUSTOMER_A)), [$this->line(1)]));
        $quotation = $this->read($id);

        self::assertSame('Q000001', $quotation?->number()->value());
        self::assertSame('Customer A', $quotation?->customerSnapshot()?->displayName()->value());
        self::assertSame('C-A', $quotation?->customerSnapshot()?->customerNumber()->value());
        self::assertSame(QuotationStatus::Draft, $quotation?->status());
        self::assertSame('20', $quotation?->total()->amount());
    }

    public function test_inactive_and_cross_tenant_customer_are_typed_and_do_not_allocate(): void
    {
        CustomerRecord::query()->whereKey(self::CUSTOMER_A)->update(['active' => false]);
        self::assertSame(QuotationWriteResult::InactiveCustomer, $this->create($this->qid(1), new CustomerId(new Uuid(self::CUSTOMER_A))));
        self::assertSame(QuotationWriteResult::CustomerNotFound, $this->create($this->qid(2), new CustomerId(new Uuid(self::CUSTOMER_B))));
        self::assertSame(1, DB::table('sales_number_sequences')->where('administration_id', self::A)->where('sequence_type', 'quotation')->value('next_value'));
    }

    public function test_persistence_conflict_rolls_number_increment_back(): void
    {
        $id = $this->qid(1);
        self::assertSame(QuotationWriteResult::Success, $this->create($id, new CustomerId(new Uuid(self::CUSTOMER_A))));
        $before = DB::table('sales_number_sequences')->where('administration_id', self::A)->where('sequence_type', 'quotation')->value('next_value');
        self::assertSame(QuotationWriteResult::DuplicateIdentity, $this->create($id, new CustomerId(new Uuid(self::CUSTOMER_A))));
        self::assertSame($before, DB::table('sales_number_sequences')->where('administration_id', self::A)->where('sequence_type', 'quotation')->value('next_value'));
    }

    public function test_draft_header_and_line_use_cases_preserve_immutable_context(): void
    {
        $id = $this->qid(1);
        $this->create($id, new CustomerId(new Uuid(self::CUSTOMER_A)));
        $original = $this->read($id);
        self::assertSame(QuotationWriteResult::Success, $this->app->make(UpdateQuotation::class)->execute($this->admin(self::A), $id, new DateTimeImmutable('2026-08-22'), null));
        self::assertSame(QuotationWriteResult::Success, $this->app->make(AddQuotationLine::class)->execute($this->admin(self::A), $id, $this->line(1)));
        self::assertSame(QuotationWriteResult::Success, $this->app->make(UpdateQuotationLine::class)->execute($this->admin(self::A), $id, $this->line(1, '3', '5')));
        self::assertSame(QuotationWriteResult::InvalidState, $this->app->make(UpdateQuotationLine::class)->execute($this->admin(self::A), $id, $this->line(2)));
        self::assertSame(QuotationWriteResult::Success, $this->app->make(RemoveQuotationLine::class)->execute($this->admin(self::A), $id, $this->line(1)->id()));
        self::assertSame(QuotationWriteResult::InvalidState, $this->app->make(RemoveQuotationLine::class)->execute($this->admin(self::A), $id, $this->line(1)->id()));

        $changed = $this->read($id);
        self::assertSame($original?->number()->value(), $changed?->number()->value());
        self::assertSame($original?->customerId()->toString(), $changed?->customerId()->toString());
        self::assertSame($original?->currency()->code(), $changed?->currency()->code());
        self::assertSame('2026-08-22', $changed?->quotationDate()->format('Y-m-d'));
        self::assertSame([], $changed?->lines());
    }

    public function test_lifecycle_use_cases_follow_domain_and_non_draft_edits_are_rejected(): void
    {
        $accepted = $this->qid(1);
        $rejected = $this->qid(2);
        $expired = $this->qid(3);
        foreach ([$accepted, $rejected, $expired] as $index => $id) {
            $this->create($id, new CustomerId(new Uuid(self::CUSTOMER_A)), [$this->line($index + 1)]);
        }
        self::assertSame(QuotationWriteResult::Success, $this->app->make(SendQuotation::class)->execute($this->admin(self::A), $accepted));
        self::assertSame(QuotationWriteResult::Success, $this->app->make(AcceptQuotation::class)->execute($this->admin(self::A), $accepted));
        self::assertSame(QuotationWriteResult::InvalidState, $this->app->make(UpdateQuotation::class)->execute($this->admin(self::A), $accepted, new DateTimeImmutable('2026-08-23'), null));
        self::assertSame(QuotationWriteResult::InvalidState, $this->app->make(RejectQuotation::class)->execute($this->admin(self::A), $accepted));

        $this->app->make(SendQuotation::class)->execute($this->admin(self::A), $rejected);
        self::assertSame(QuotationWriteResult::Success, $this->app->make(RejectQuotation::class)->execute($this->admin(self::A), $rejected));
        self::assertSame(QuotationWriteResult::Success, $this->app->make(ExpireQuotation::class)->execute($this->admin(self::A), $expired));
        self::assertSame(QuotationStatus::Accepted, $this->read($accepted)?->status());
        self::assertSame(QuotationStatus::Rejected, $this->read($rejected)?->status());
        self::assertSame(QuotationStatus::Expired, $this->read($expired)?->status());
        self::assertSame(QuotationWriteResult::NotFound, $this->app->make(SendQuotation::class)->execute($this->admin(self::B), $accepted));
    }

    public function test_currency_mismatch_and_empty_send_are_typed_invalid_state(): void
    {
        $id = $this->qid(1);
        $this->create($id, new CustomerId(new Uuid(self::CUSTOMER_A)));
        self::assertSame(QuotationWriteResult::InvalidState, $this->app->make(AddQuotationLine::class)->execute($this->admin(self::A), $id, $this->line(1, currency: 'USD')));
        self::assertSame(QuotationWriteResult::InvalidState, $this->app->make(SendQuotation::class)->execute($this->admin(self::A), $id));
    }

    /** @param list<QuotationLine> $lines */
    private function create(QuotationId $id, CustomerId $customerId, array $lines = []): QuotationWriteResult
    {
        return $this->app->make(CreateQuotation::class)->execute($this->admin(self::A), $id, $customerId, new Currency('EUR'), new DateTimeImmutable('2026-08-21'), new DateTimeImmutable('2026-09-21'), $lines);
    }

    private function read(QuotationId $id): ?Quotation
    {
        return $this->app->make(QuotationReadRepository::class)->findForAdministration($this->admin(self::A), $id);
    }

    private function line(int $id, string $quantity = '2', string $amount = '10', string $currency = 'EUR'): QuotationLine
    {
        return new QuotationLine(new QuotationLineId(new Uuid(sprintf('f1000000-0000-4000-8000-%012d', $id))), new LineDescription('Service'), new Quantity($quantity), new Money($amount, new Currency($currency)));
    }

    private function qid(int $id): QuotationId
    {
        return new QuotationId(new Uuid(sprintf('f2000000-0000-4000-8000-%012d', $id)));
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
