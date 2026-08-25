<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure;

use App\Application\Sales\CreateDeliveryRequestStatus;
use App\Application\Sales\CreateSalesDocumentDeliveryRequest;
use App\Application\Sales\DeliveryOutboxStore;
use App\Application\Sales\DeliveryRequestStore;
use App\Application\Sales\DocumentMailMessage;
use App\Application\Sales\DocumentMailTransport;
use App\Application\Sales\DocumentMailTransportResult;
use App\Application\Sales\ProcessSalesDocumentDelivery;
use App\Application\Sales\SalesDocumentDeliveryHistoryReader;
use App\Application\Sales\SalesDocumentSource;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Sales\Entities\DeliveryRequest;
use App\Domain\Sales\Enums\DeliveryRequestStatus;
use App\Domain\Sales\Enums\SalesDocumentType;
use App\Domain\Sales\ValueObjects\ArtifactId;
use App\Domain\Sales\ValueObjects\DeliveryOutboxMessageId;
use App\Domain\Sales\ValueObjects\DeliveryRequestId;
use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Shared\Identity\Uuid;
use App\Jobs\ProcessSalesDocumentDeliveryJob;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Large;
use Tests\TestCase;
use Throwable;

#[Large]
final class SalesDocumentDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = 'd1000000-0000-4000-8000-000000000001';

    private const ADMIN_B = 'd1000000-0000-4000-8000-000000000002';

    private const USER = 'd2000000-0000-4000-8000-000000000001';

    private const QUOTATION = 'd3000000-0000-4000-8000-000000000001';

    private const ARTIFACT = 'd4000000-0000-4000-8000-000000000001';

    private const REQUEST = 'd5000000-0000-4000-8000-000000000001';

    private FakeDocumentMailTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('sales_documents');
        $this->transport = new FakeDocumentMailTransport;
        $this->app->instance(DocumentMailTransport::class, $this->transport);
        $this->seedSource();
    }

    public function test_request_outbox_worker_and_accepted_attempt_are_durable_and_idempotent(): void
    {
        $store = $this->app->make(DeliveryRequestStore::class);
        $request = $this->request();
        self::assertSame(CreateDeliveryRequestStatus::Created, $store->createWithInitialOutbox($request)->status);
        Queue::assertPushed(ProcessSalesDocumentDeliveryJob::class, fn (ProcessSalesDocumentDeliveryJob $job): bool => $job->outboxMessageId === DB::table('sales_document_delivery_outbox')->value('id'));
        self::assertSame(CreateDeliveryRequestStatus::Existing, $store->createWithInitialOutbox($request)->status);
        self::assertDatabaseCount('sales_document_delivery_requests', 1);
        self::assertDatabaseCount('sales_document_delivery_outbox', 1);

        $this->process();

        self::assertSame(1, $this->transport->sends);
        self::assertSame('customer@example.test', $this->transport->last?->toEmail);
        self::assertSame('sender@example.test', $this->transport->last?->fromEmail);
        self::assertSame('%PDF-delivery', $this->transport->last?->attachmentBytes);
        self::assertDatabaseHas('sales_document_delivery_attempts', ['attempt_number' => 1, 'result' => 'accepted_by_transport']);
        self::assertDatabaseHas('sales_document_delivery_requests', ['id' => self::REQUEST, 'status' => 'accepted_by_transport']);
        self::assertDatabaseHas('sales_document_delivery_outbox', ['delivery_request_id' => self::REQUEST, 'status' => 'processed']);

        $this->process();
        self::assertSame(1, $this->transport->sends);
        self::assertDatabaseCount('sales_document_delivery_attempts', 1);
    }

    public function test_create_snapshots_authoritative_recipient_sender_subject_body_and_template(): void
    {
        $result = $this->app->make(CreateSalesDocumentDeliveryRequest::class)->execute($this->admin(self::ADMIN), $this->requestId(), SalesDocumentSource::quotation(new QuotationId(new Uuid(self::QUOTATION))), new ArtifactId(new Uuid(self::ARTIFACT)), new UserId(new Uuid(self::USER)));
        self::assertSame(CreateDeliveryRequestStatus::Created, $result->status);
        self::assertSame('preferred@example.test', $result->request?->recipientEmail);
        self::assertSame('Preferred Contact', $result->request?->recipientName);
        self::assertSame('Demo Sender', $result->request?->fromName);
        self::assertSame('sender@example.test', $result->request?->fromEmail);
        self::assertSame('reply@example.test', $result->request?->replyTo);
        self::assertSame('Offerte Q000001', $result->request?->subject);
        self::assertStringContainsString('Q000001', (string) $result->request?->body);
        self::assertSame('quotation-mail-v1', $result->request?->templateVersion);
    }

    public function test_idempotency_conflict_and_tenant_scoped_lookup_and_history(): void
    {
        $store = $this->app->make(DeliveryRequestStore::class);
        $store->createWithInitialOutbox($this->request());
        self::assertSame(CreateDeliveryRequestStatus::IdempotencyConflict, $store->createWithInitialOutbox($this->request('different'))->status);
        self::assertNull($store->find($this->admin(self::ADMIN_B), $this->requestId()));
        $history = $this->app->make(SalesDocumentDeliveryHistoryReader::class)->history($this->admin(self::ADMIN_B), SalesDocumentSource::quotation(new QuotationId(new Uuid(self::QUOTATION))));
        self::assertSame([], $history->requests);
        self::assertSame([], $history->attempts);
    }

    public function test_database_rejects_cross_tenant_artifact_and_source_reference(): void
    {
        $this->expectException(QueryException::class);
        DB::table('sales_document_delivery_requests')->insert([
            'id' => 'd5000000-0000-4000-8000-000000000099', 'administration_id' => self::ADMIN_B,
            'document_type' => 'quotation', 'quotation_id' => self::QUOTATION, 'sales_invoice_id' => null, 'sales_credit_invoice_id' => null,
            'artifact_id' => self::ARTIFACT, 'recipient_email' => 'x@example.test', 'recipient_name' => 'X', 'recipient_source' => 'preference',
            'from_name' => 'Sender', 'from_email' => 'sender@example.test', 'subject' => 'Subject', 'body' => 'Body',
            'template_version' => 'quotation-mail-v1', 'semantic_fingerprint' => hash('sha256', 'cross-tenant'), 'initiated_by' => self::USER,
            'status' => 'prepared', 'requested_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_retryable_failure_appends_attempt_and_reuses_snapshots(): void
    {
        $this->transport->results = [DocumentMailTransportResult::failed('temporary_network', true), DocumentMailTransportResult::accepted('provider-2')];
        $this->app->make(DeliveryRequestStore::class)->createWithInitialOutbox($this->request());
        $this->process();
        DB::table('sales_document_delivery_outbox')->where('delivery_request_id', self::REQUEST)->update(['available_at' => now()->subSecond()]);
        $this->process();
        self::assertSame(2, $this->transport->sends);
        self::assertSame(['failed_transport', 'accepted_by_transport'], DB::table('sales_document_delivery_attempts')->orderBy('attempt_number')->pluck('result')->all());
        self::assertSame([self::ARTIFACT], DB::table('sales_document_delivery_attempts')->distinct()->pluck('artifact_id')->all());
        self::assertDatabaseHas('sales_document_delivery_requests', ['id' => self::REQUEST, 'recipient_email' => 'customer@example.test', 'from_email' => 'sender@example.test', 'status' => 'accepted_by_transport']);
    }

    public function test_outcome_unknown_blocks_automatic_duplicate_send_and_source_status_is_unchanged(): void
    {
        $this->transport->throw = true;
        $this->app->make(DeliveryRequestStore::class)->createWithInitialOutbox($this->request());
        $this->process();
        $this->process();
        self::assertSame(1, $this->transport->sends);
        self::assertDatabaseHas('sales_document_delivery_attempts', ['result' => 'outcome_unknown', 'retryable' => false]);
        self::assertDatabaseHas('sales_document_delivery_outbox', ['status' => 'blocked']);
        self::assertSame('draft', DB::table('quotations')->where('id', self::QUOTATION)->value('status'));
    }

    public function test_two_real_mysql_workers_cannot_claim_the_same_external_send(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required.');
        }
        $this->app->make(DeliveryRequestStore::class)->createWithInitialOutbox($this->request());
        $outboxId = (string) DB::table('sales_document_delivery_outbox')->where('delivery_request_id', self::REQUEST)->value('id');
        DB::commit();
        $files = [tempnam(sys_get_temp_dir(), 'delivery-worker-a-'), tempnam(sys_get_temp_dir(), 'delivery-worker-b-')];
        $children = [];
        foreach ($files as $file) {
            self::assertIsString($file);
            $pid = pcntl_fork();
            self::assertNotSame(-1, $pid);
            if ($pid === 0) {
                try {
                    DB::purge();
                    $claimed = app(DeliveryOutboxStore::class)->claim(new DeliveryOutboxMessageId(new Uuid($outboxId)));
                    file_put_contents($file, $claimed === null ? 'not-claimed' : 'claimed');
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
        $claims = array_map(static fn (string $file): string => trim((string) file_get_contents($file)), $files);
        sort($claims);
        self::assertSame(['claimed', 'not-claimed'], $claims);
        self::assertSame(1, DB::table('sales_document_delivery_attempts')->count());
        foreach ($files as $file) {
            unlink($file);
        }
        $this->cleanupCommittedFixture();
        DB::beginTransaction();
    }

    private function process(): void
    {
        $id = DB::table('sales_document_delivery_outbox')->where('delivery_request_id', self::REQUEST)->value('id');
        $this->app->make(ProcessSalesDocumentDelivery::class)->execute(new DeliveryOutboxMessageId(new Uuid($id)));
    }

    private function request(string $fingerprint = 'same'): DeliveryRequest
    {
        return new DeliveryRequest($this->requestId(), $this->admin(self::ADMIN), SalesDocumentType::Quotation, self::QUOTATION, new ArtifactId(new Uuid(self::ARTIFACT)), 'customer@example.test', 'Customer', null, 'preference', 'Demo', 'sender@example.test', 'reply@example.test', 'Offerte Q000001', 'Beste Customer', 'quotation-mail-v1', hash('sha256', $fingerprint), new UserId(new Uuid(self::USER)), DeliveryRequestStatus::Prepared, new DateTimeImmutable('2026-08-25T12:00:00+00:00'));
    }

    private function requestId(): DeliveryRequestId
    {
        return new DeliveryRequestId(new Uuid(self::REQUEST));
    }

    private function admin(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }

    private function seedSource(): void
    {
        foreach ([self::ADMIN => 'A', self::ADMIN_B => 'B'] as $id => $code) {
            DB::table('administrations')->insert(['id' => $id, 'code' => 'DEL-'.$code, 'name' => 'Delivery '.$code, 'base_currency' => 'EUR', 'status' => 'active', 'document_sender_name' => $id === self::ADMIN ? 'Demo Sender' : null, 'document_sender_email' => $id === self::ADMIN ? 'sender@example.test' : null, 'document_reply_to_email' => $id === self::ADMIN ? 'reply@example.test' : null, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('domain_users')->insert(['id' => self::USER, 'display_name' => 'Initiator', 'email' => 'initiator@example.test', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('relations')->insert(['id' => 'd6000000-0000-4000-8000-000000000001', 'administration_id' => self::ADMIN, 'code' => 'REL-1', 'display_name' => 'Customer', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('relation_contacts')->insert(['contact_id' => 'd6100000-0000-4000-8000-000000000001', 'administration_id' => self::ADMIN, 'relation_id' => 'd6000000-0000-4000-8000-000000000001', 'contact_name' => 'Preferred Contact', 'email' => 'preferred@example.test', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sales_document_recipient_preferences')->insert(['id' => 'd6200000-0000-4000-8000-000000000001', 'administration_id' => self::ADMIN, 'relation_id' => 'd6000000-0000-4000-8000-000000000001', 'purpose' => 'quotation', 'contact_id' => 'd6100000-0000-4000-8000-000000000001', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('customers')->insert(['id' => 'd7000000-0000-4000-8000-000000000001', 'administration_id' => self::ADMIN, 'relation_id' => 'd6000000-0000-4000-8000-000000000001', 'customer_number' => 'C000001', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('quotations')->insert(['id' => self::QUOTATION, 'administration_id' => self::ADMIN, 'quotation_number' => 'Q000001', 'customer_id' => 'd7000000-0000-4000-8000-000000000001', 'customer_relation_id_snapshot' => 'd6000000-0000-4000-8000-000000000001', 'customer_number_snapshot' => 'C000001', 'customer_name_snapshot' => 'Customer', 'quotation_address_id_snapshot' => 'd8000000-0000-4000-8000-000000000001', 'quotation_address_type_snapshot' => 'quotation', 'quotation_address_line_1_snapshot' => 'Street 1', 'quotation_postal_code_snapshot' => '1000AA', 'quotation_city_snapshot' => 'City', 'quotation_country_code_snapshot' => 'NL', 'currency' => 'EUR', 'status' => 'draft', 'quotation_date' => '2026-08-25', 'created_at' => now(), 'updated_at' => now()]);
        $bytes = '%PDF-delivery';
        $key = self::ADMIN.'/sales-document-artifacts/'.self::ARTIFACT.'.pdf';
        Storage::disk('sales_documents')->put($key, $bytes);
        DB::table('document_artifacts')->insert(['id' => self::ARTIFACT, 'administration_id' => self::ADMIN, 'document_type' => 'quotation', 'version' => 1, 'mime_type' => 'application/pdf', 'filename' => 'Offerte-Q000001.pdf', 'storage_key' => $key, 'sha256' => hash('sha256', $bytes), 'byte_size' => strlen($bytes), 'generated_at' => now(), 'template_version' => 'quotation-v1', 'renderer_version' => 'test', 'render_fingerprint' => hash('sha256', 'render'), 'locale' => 'nl', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('quotation_document_artifacts')->insert(['artifact_id' => self::ARTIFACT, 'administration_id' => self::ADMIN, 'quotation_id' => self::QUOTATION, 'render_fingerprint' => hash('sha256', 'render'), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function cleanupCommittedFixture(): void
    {
        $admins = [self::ADMIN, self::ADMIN_B];
        DB::table('sales_document_delivery_outbox')->whereIn('administration_id', $admins)->delete();
        DB::table('sales_document_delivery_attempts')->whereIn('administration_id', $admins)->delete();
        DB::table('sales_document_delivery_requests')->whereIn('administration_id', $admins)->delete();
        DB::table('quotation_document_artifacts')->whereIn('administration_id', $admins)->delete();
        DB::table('document_artifacts')->whereIn('administration_id', $admins)->delete();
        DB::table('quotation_lines')->whereIn('administration_id', $admins)->delete();
        DB::table('quotations')->whereIn('administration_id', $admins)->delete();
        DB::table('customers')->whereIn('administration_id', $admins)->delete();
        DB::table('sales_document_recipient_preferences')->whereIn('administration_id', $admins)->delete();
        DB::table('relation_contacts')->whereIn('administration_id', $admins)->delete();
        DB::table('relations')->whereIn('administration_id', $admins)->delete();
        DB::table('domain_users')->where('id', self::USER)->delete();
        DB::table('administrations')->whereIn('id', $admins)->delete();
    }
}

final class FakeDocumentMailTransport implements DocumentMailTransport
{
    public int $sends = 0;

    public bool $throw = false;

    /** @var list<DocumentMailTransportResult> */
    public array $results = [];

    public ?DocumentMailMessage $last = null;

    public function send(DocumentMailMessage $message): DocumentMailTransportResult
    {
        $this->sends++;
        $this->last = $message;
        if ($this->throw) {
            throw new \RuntimeException('Ambiguous transport boundary.');
        }

        return array_shift($this->results) ?? DocumentMailTransportResult::accepted('provider-1');
    }

    public function identifier(): string
    {
        return 'fake';
    }
}
