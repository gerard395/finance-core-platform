<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure;

use App\Application\Sales\CreateDeliveryRequestStatus;
use App\Application\Sales\CreateSalesDocumentDeliveryRequest;
use App\Application\Sales\DeliveryInfrastructureReadinessStatus;
use App\Application\Sales\DeliveryOutboxStore;
use App\Application\Sales\DeliveryOutcomeResolutionStatus;
use App\Application\Sales\DeliveryOutcomeResolutionStore;
use App\Application\Sales\DeliveryRequestStore;
use App\Application\Sales\DeliveryWorkerHeartbeatStore;
use App\Application\Sales\DocumentMailMessage;
use App\Application\Sales\DocumentMailTransport;
use App\Application\Sales\DocumentMailTransportResult;
use App\Application\Sales\ProcessSalesDocumentDelivery;
use App\Application\Sales\QueueSalesDocumentDelivery;
use App\Application\Sales\ReconcileQuotationDeliveryLifecycle;
use App\Application\Sales\RenderedSalesDocument;
use App\Application\Sales\ResolveUnknownDeliveryOutcome;
use App\Application\Sales\SalesDocumentDeliveryHistoryReader;
use App\Application\Sales\SalesDocumentDeliveryInfrastructureReadiness;
use App\Application\Sales\SalesDocumentDeliveryReadinessStatus;
use App\Application\Sales\SalesDocumentPdfRenderer;
use App\Application\Sales\SalesDocumentRenderModel;
use App\Application\Sales\SalesDocumentSource;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Identity\Definitions\DeliveryOperationsRole;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Sales\Entities\DeliveryOutcomeResolution;
use App\Domain\Sales\Entities\DeliveryRequest;
use App\Domain\Sales\Enums\DeliveryOutcomeResolutionType;
use App\Domain\Sales\Enums\DeliveryRequestStatus;
use App\Domain\Sales\Enums\SalesDocumentType;
use App\Domain\Sales\ValueObjects\ArtifactId;
use App\Domain\Sales\ValueObjects\DeliveryAttemptId;
use App\Domain\Sales\ValueObjects\DeliveryOutboxMessageId;
use App\Domain\Sales\ValueObjects\DeliveryOutcomeResolutionId;
use App\Domain\Sales\ValueObjects\DeliveryRequestId;
use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Identity\DeliveryOperationsAuthorizationProvisioner;
use App\Jobs\ProcessSalesDocumentDeliveryJob;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
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
        self::assertDatabaseHas('quotations', ['id' => self::QUOTATION, 'status' => 'sent']);

        DB::table('quotations')->where('id', self::QUOTATION)->update(['status' => 'draft']);
        self::assertSame(1, $this->app->make(ReconcileQuotationDeliveryLifecycle::class)->reconcilePending());
        self::assertDatabaseHas('quotations', ['id' => self::QUOTATION, 'status' => 'sent']);
        self::assertSame(1, $this->transport->sends);

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

    public function test_heartbeat_readiness_is_fresh_stale_and_environment_aware(): void
    {
        config(['queue.default' => 'database']);
        $readiness = $this->app->make(SalesDocumentDeliveryInfrastructureReadiness::class);
        self::assertSame(DeliveryInfrastructureReadinessStatus::WorkerUnavailable, $readiness->check()->status);
        $this->app->make(DeliveryWorkerHeartbeatStore::class)->beat('test-worker', 'test-release');
        self::assertSame(DeliveryInfrastructureReadinessStatus::Ready, $readiness->check()->status);
        DB::table('delivery_worker_heartbeats')->update(['last_seen_at' => now()->subSeconds(90)]);
        self::assertSame(DeliveryInfrastructureReadinessStatus::WorkerUnavailable, $readiness->check()->status);
        DB::table('delivery_worker_heartbeats')->update(['last_seen_at' => now()]);
        $this->app->detectEnvironment(fn (): string => 'production');
        config(['mail.default' => 'log']);
        self::assertSame(DeliveryInfrastructureReadinessStatus::MailTransportUnavailable, $readiness->check()->status);
    }

    public function test_health_command_has_safe_output_and_meaningful_exit_codes(): void
    {
        config(['queue.default' => 'database']);
        $this->artisan('delivery:health')->assertExitCode(1)->expectsOutputToContain('worker_unavailable');
        $this->app->make(DeliveryWorkerHeartbeatStore::class)->beat('test-worker');
        $this->artisan('delivery:health')->assertExitCode(0)->expectsOutputToContain('ready')->doesntExpectOutputToContain('customer@example.test')->doesntExpectOutputToContain('MAIL_PASSWORD');
    }

    public function test_web_orchestration_requires_worker_readiness_and_double_submit_is_idempotent(): void
    {
        config(['queue.default' => 'database']);
        $this->app->instance(SalesDocumentPdfRenderer::class, new FakeSalesDocumentPdfRenderer);
        $queue = $this->app->make(QueueSalesDocumentDelivery::class);
        $requestId = new DeliveryRequestId(new Uuid('d5000000-0000-4000-8000-000000000099'));
        $source = SalesDocumentSource::quotation(new QuotationId(new Uuid(self::QUOTATION)));

        self::assertSame(SalesDocumentDeliveryReadinessStatus::InfrastructureUnavailable, $queue->execute($this->admin(self::ADMIN), $requestId, $source, new UserId(new Uuid(self::USER)), false)->status);
        self::assertDatabaseCount('sales_document_delivery_requests', 0);
        $this->app->make(DeliveryWorkerHeartbeatStore::class)->beat('web-flow-worker');
        self::assertTrue($queue->execute($this->admin(self::ADMIN), $requestId, $source, new UserId(new Uuid(self::USER)), false)->queued());
        self::assertTrue($queue->execute($this->admin(self::ADMIN), $requestId, $source, new UserId(new Uuid(self::USER)), false)->replayed);
        self::assertDatabaseCount('sales_document_delivery_requests', 1);
        self::assertDatabaseCount('sales_document_delivery_outbox', 1);
    }

    public function test_stale_pre_send_is_recovered_but_transport_started_becomes_unknown(): void
    {
        $store = $this->app->make(DeliveryRequestStore::class);
        $outbox = $this->app->make(DeliveryOutboxStore::class);
        $store->createWithInitialOutbox($this->request());
        $outboxId = new DeliveryOutboxMessageId(new Uuid((string) DB::table('sales_document_delivery_outbox')->value('id')));
        $claimed = $outbox->claim($outboxId);
        self::assertNotNull($claimed);
        DB::table('sales_document_delivery_outbox')->update(['lease_expires_at' => now()->subSecond()]);
        self::assertSame(1, $outbox->recoverStalePreSend());
        self::assertDatabaseHas('sales_document_delivery_outbox', ['status' => 'available']);
        self::assertDatabaseHas('sales_document_delivery_attempts', ['result' => 'failed_transport', 'error_category' => 'pre_send_worker_crash']);

        $claimedAgain = $outbox->claim($outboxId);
        self::assertNotNull($claimedAgain);
        self::assertTrue($outbox->markTransportStarted($claimedAgain));
        DB::table('sales_document_delivery_outbox')->update(['lease_expires_at' => now()->subSecond()]);
        self::assertSame(0, $outbox->recoverStalePreSend());
        self::assertDatabaseHas('sales_document_delivery_attempts', ['id' => $claimedAgain->attempt->id->toString(), 'result' => 'outcome_unknown']);
        self::assertDatabaseHas('sales_document_delivery_outbox', ['status' => 'blocked']);
    }

    #[DataProvider('resolutionTypes')]
    public function test_unknown_resolution_is_authorized_tenant_scoped_append_only_and_does_not_send(DeliveryOutcomeResolutionType $type): void
    {
        $this->transport->throw = true;
        $this->app->make(DeliveryRequestStore::class)->createWithInitialOutbox($this->request());
        $this->process();
        $attemptId = (string) DB::table('sales_document_delivery_attempts')->value('id');
        $this->app->make(DeliveryOperationsAuthorizationProvisioner::class)->provision();
        DB::table('administration_memberships')->insert(['id' => 'd9000000-0000-4000-8000-000000000001', 'user_id' => self::USER, 'administration_id' => self::ADMIN, 'active' => true, 'valid_from' => now()->subDay(), 'valid_until' => now()->addDay(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('administration_membership_roles')->insert(['id' => 'd9100000-0000-4000-8000-000000000001', 'membership_id' => 'd9000000-0000-4000-8000-000000000001', 'role_id' => DeliveryOperationsRole::Operator->id()->toString(), 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $useCase = $this->app->make(ResolveUnknownDeliveryOutcome::class);
        $result = $useCase->execute(new DeliveryOutcomeResolutionId(new Uuid('d9200000-0000-4000-8000-000000000001')), $this->admin(self::ADMIN), $this->requestId(), new DeliveryAttemptId(new Uuid($attemptId)), $type, new UserId(new Uuid(self::USER)), 'Extern onderzocht');
        self::assertSame(DeliveryOutcomeResolutionStatus::Resolved, $result);
        $this->app->make(ReconcileQuotationDeliveryLifecycle::class)->reconcilePending();
        self::assertSame($type === DeliveryOutcomeResolutionType::HandledExternally ? 'sent' : 'draft', DB::table('quotations')->where('id', self::QUOTATION)->value('status'));
        self::assertSame(DeliveryOutcomeResolutionStatus::AlreadyResolved, $useCase->execute(new DeliveryOutcomeResolutionId(new Uuid('d9200000-0000-4000-8000-000000000002')), $this->admin(self::ADMIN), $this->requestId(), new DeliveryAttemptId(new Uuid($attemptId)), DeliveryOutcomeResolutionType::AuthorizeResend, new UserId(new Uuid(self::USER))));
        self::assertDatabaseHas('sales_document_delivery_attempts', ['id' => $attemptId, 'result' => 'outcome_unknown']);
        self::assertDatabaseHas('sales_document_delivery_outcome_resolutions', ['delivery_attempt_id' => $attemptId, 'resolution_type' => $type->value, 'resolved_by' => self::USER]);
        self::assertSame(1, $this->transport->sends);
        DB::table('administration_membership_roles')->update(['active' => false]);
        self::assertSame(DeliveryOutcomeResolutionStatus::Unauthorized, $useCase->execute(new DeliveryOutcomeResolutionId(new Uuid('d9200000-0000-4000-8000-000000000003')), $this->admin(self::ADMIN), $this->requestId(), new DeliveryAttemptId(new Uuid($attemptId)), DeliveryOutcomeResolutionType::AuthorizeResend, new UserId(new Uuid(self::USER))));
        self::assertSame(DeliveryOutcomeResolutionStatus::Unauthorized, $useCase->execute(new DeliveryOutcomeResolutionId(new Uuid('d9200000-0000-4000-8000-000000000004')), $this->admin(self::ADMIN_B), $this->requestId(), new DeliveryAttemptId(new Uuid($attemptId)), DeliveryOutcomeResolutionType::AuthorizeResend, new UserId(new Uuid(self::USER))));
    }

    /** @return array<string, array{DeliveryOutcomeResolutionType}> */
    public static function resolutionTypes(): array
    {
        return [
            'handled externally' => [DeliveryOutcomeResolutionType::HandledExternally],
            'authorize resend' => [DeliveryOutcomeResolutionType::AuthorizeResend],
        ];
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
            self::assertSame(0, pcntl_wexitstatus($status), implode(' | ', array_map(static fn (string $file): string => (string) file_get_contents($file), $files)));
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

    public function test_two_real_mysql_recovery_workers_recover_a_stale_pre_send_claim_once(): void
    {
        $this->app->make(DeliveryRequestStore::class)->createWithInitialOutbox($this->request());
        $outboxId = new DeliveryOutboxMessageId(new Uuid((string) DB::table('sales_document_delivery_outbox')->value('id')));
        self::assertNotNull($this->app->make(DeliveryOutboxStore::class)->claim($outboxId));
        DB::table('sales_document_delivery_outbox')->update(['lease_expires_at' => now()->subSecond()]);
        DB::commit();

        $results = $this->runConcurrently(static fn (): string => (string) app(DeliveryOutboxStore::class)->recoverStalePreSend());
        sort($results);
        self::assertSame(['0', '1'], $results);
        self::assertSame(1, DB::table('sales_document_delivery_attempts')->where('error_category', 'pre_send_worker_crash')->count());
        $this->cleanupCommittedFixture();
        DB::beginTransaction();
    }

    public function test_two_real_mysql_resolution_workers_create_one_authoritative_resolution(): void
    {
        $this->transport->throw = true;
        $this->app->make(DeliveryRequestStore::class)->createWithInitialOutbox($this->request());
        $this->process();
        $attemptId = (string) DB::table('sales_document_delivery_attempts')->value('id');
        DB::commit();

        $results = $this->runConcurrently(function () use ($attemptId): string {
            $resolution = new DeliveryOutcomeResolution(
                new DeliveryOutcomeResolutionId(new Uuid(Str::uuid()->toString())),
                $this->admin(self::ADMIN),
                $this->requestId(),
                new DeliveryAttemptId(new Uuid($attemptId)),
                DeliveryOutcomeResolutionType::HandledExternally,
                new UserId(new Uuid(self::USER)),
                new DateTimeImmutable,
            );

            return app(DeliveryOutcomeResolutionStore::class)->appendForUnknownAttempt($resolution)->value;
        });
        sort($results);
        self::assertSame(['already_resolved', 'resolved'], $results);
        self::assertSame(1, DB::table('sales_document_delivery_outcome_resolutions')->where('delivery_attempt_id', $attemptId)->count());
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
            DB::table('administrations')->insert(['id' => $id, 'code' => 'DEL-'.$code, 'name' => 'Delivery '.$code, 'base_currency' => 'EUR', 'status' => 'active', 'organisation_display_name' => $id === self::ADMIN ? 'Demo' : null, 'organisation_chamber_of_commerce_number' => $id === self::ADMIN ? '12345678' : null, 'document_address_line_1' => $id === self::ADMIN ? 'Issuer Street 1' : null, 'document_postal_code' => $id === self::ADMIN ? '1000AA' : null, 'document_city' => $id === self::ADMIN ? 'City' : null, 'document_country_code' => $id === self::ADMIN ? 'NL' : null, 'document_sender_name' => $id === self::ADMIN ? 'Demo Sender' : null, 'document_sender_email' => $id === self::ADMIN ? 'sender@example.test' : null, 'document_reply_to_email' => $id === self::ADMIN ? 'reply@example.test' : null, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('domain_users')->insert(['id' => self::USER, 'display_name' => 'Initiator', 'email' => 'initiator@example.test', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('relations')->insert(['id' => 'd6000000-0000-4000-8000-000000000001', 'administration_id' => self::ADMIN, 'code' => 'REL-1', 'display_name' => 'Customer', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('relation_contacts')->insert(['contact_id' => 'd6100000-0000-4000-8000-000000000001', 'administration_id' => self::ADMIN, 'relation_id' => 'd6000000-0000-4000-8000-000000000001', 'contact_name' => 'Preferred Contact', 'email' => 'preferred@example.test', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sales_document_recipient_preferences')->insert(['id' => 'd6200000-0000-4000-8000-000000000001', 'administration_id' => self::ADMIN, 'relation_id' => 'd6000000-0000-4000-8000-000000000001', 'purpose' => 'quotation', 'contact_id' => 'd6100000-0000-4000-8000-000000000001', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('customers')->insert(['id' => 'd7000000-0000-4000-8000-000000000001', 'administration_id' => self::ADMIN, 'relation_id' => 'd6000000-0000-4000-8000-000000000001', 'customer_number' => 'C000001', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('quotations')->insert(['id' => self::QUOTATION, 'administration_id' => self::ADMIN, 'quotation_number' => 'Q000001', 'customer_id' => 'd7000000-0000-4000-8000-000000000001', 'customer_relation_id_snapshot' => 'd6000000-0000-4000-8000-000000000001', 'customer_number_snapshot' => 'C000001', 'customer_name_snapshot' => 'Customer', 'quotation_address_id_snapshot' => 'd8000000-0000-4000-8000-000000000001', 'quotation_address_type_snapshot' => 'quotation', 'quotation_address_line_1_snapshot' => 'Street 1', 'quotation_postal_code_snapshot' => '1000AA', 'quotation_city_snapshot' => 'City', 'quotation_country_code_snapshot' => 'NL', 'currency' => 'EUR', 'status' => 'draft', 'quotation_date' => '2026-08-25', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('quotation_lines')->insert(['id' => 'd8100000-0000-4000-8000-000000000001', 'administration_id' => self::ADMIN, 'quotation_id' => self::QUOTATION, 'description' => 'Service', 'quantity' => '1', 'unit_price_amount' => '100', 'currency' => 'EUR', 'created_at' => now(), 'updated_at' => now()]);
        $bytes = '%PDF-delivery';
        $key = self::ADMIN.'/sales-document-artifacts/'.self::ARTIFACT.'.pdf';
        Storage::disk('sales_documents')->put($key, $bytes);
        DB::table('document_artifacts')->insert(['id' => self::ARTIFACT, 'administration_id' => self::ADMIN, 'document_type' => 'quotation', 'version' => 1, 'mime_type' => 'application/pdf', 'filename' => 'Offerte-Q000001.pdf', 'storage_key' => $key, 'sha256' => hash('sha256', $bytes), 'byte_size' => strlen($bytes), 'generated_at' => now(), 'template_version' => 'quotation-v1', 'renderer_version' => 'test', 'render_fingerprint' => hash('sha256', 'render'), 'locale' => 'nl', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('quotation_document_artifacts')->insert(['artifact_id' => self::ARTIFACT, 'administration_id' => self::ADMIN, 'quotation_id' => self::QUOTATION, 'render_fingerprint' => hash('sha256', 'render'), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function cleanupCommittedFixture(): void
    {
        $admins = [self::ADMIN, self::ADMIN_B];
        DB::table('sales_document_delivery_outcome_resolutions')->whereIn('administration_id', $admins)->delete();
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

    /** @return list<string> */
    private function runConcurrently(callable $operation): array
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required.');
        }
        $files = [tempnam(sys_get_temp_dir(), 'delivery-concurrency-a-'), tempnam(sys_get_temp_dir(), 'delivery-concurrency-b-')];
        $children = [];
        foreach ($files as $file) {
            self::assertIsString($file);
            $pid = pcntl_fork();
            self::assertNotSame(-1, $pid);
            if ($pid === 0) {
                try {
                    DB::purge();
                    file_put_contents($file, $operation());
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
            self::assertSame(0, pcntl_wexitstatus($status), implode(' | ', array_map(static fn (string $file): string => (string) file_get_contents($file), $files)));
        }
        $results = array_map(static fn (string $file): string => trim((string) file_get_contents($file)), $files);
        foreach ($files as $file) {
            unlink($file);
        }

        return $results;
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

final class FakeSalesDocumentPdfRenderer implements SalesDocumentPdfRenderer
{
    public function render(SalesDocumentRenderModel $model): RenderedSalesDocument
    {
        return new RenderedSalesDocument('%PDF-W4E-004-'.$model->number, 'fake-web-renderer');
    }
}
