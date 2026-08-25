<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Sales\ClaimedDelivery;
use App\Application\Sales\CreateDeliveryRequestResult;
use App\Application\Sales\CreateDeliveryRequestStatus;
use App\Application\Sales\DeliveryIdentityGenerator;
use App\Application\Sales\DeliveryOutboxStore;
use App\Application\Sales\DeliveryRequestStore;
use App\Application\Sales\DocumentMailTransport;
use App\Application\Sales\DocumentMailTransportResult;
use App\Application\Sales\QuotationDeliveryLifecycleCandidate;
use App\Application\Sales\QuotationDeliveryLifecycleCandidates;
use App\Application\Sales\SalesDocumentDeliveryHistory;
use App\Application\Sales\SalesDocumentDeliveryHistoryReader;
use App\Application\Sales\SalesDocumentDeliverySource;
use App\Application\Sales\SalesDocumentDeliverySourceReader;
use App\Application\Sales\SalesDocumentSource;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Sales\Entities\DeliveryAttempt;
use App\Domain\Sales\Entities\DeliveryRequest;
use App\Domain\Sales\Enums\DeliveryAttemptResult;
use App\Domain\Sales\Enums\DeliveryOutboxStatus;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class EloquentSalesDocumentDelivery implements DeliveryOutboxStore, DeliveryRequestStore, QuotationDeliveryLifecycleCandidates, SalesDocumentDeliveryHistoryReader, SalesDocumentDeliverySourceReader
{
    private const MAX_ATTEMPTS = 3;

    public function __construct(private DeliveryIdentityGenerator $identities, private DocumentMailTransport $transport) {}

    public function createWithInitialOutbox(DeliveryRequest $request): CreateDeliveryRequestResult
    {
        $outboxId = $this->identities->outboxId();
        try {
            DB::transaction(function () use ($request, $outboxId): void {
                DB::table('sales_document_delivery_requests')->insert($this->requestRow($request));
                DB::table('sales_document_delivery_outbox')->insert([
                    'id' => $outboxId->toString(), 'administration_id' => $request->administrationId->toString(), 'delivery_request_id' => $request->id->toString(),
                    'intent_type' => 'initial_delivery', 'status' => DeliveryOutboxStatus::Available->value, 'available_at' => now(), 'processing_attempts' => 0,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            });
        } catch (QueryException $exception) {
            $existing = $this->find($request->administrationId, $request->id);
            if ($existing === null) {
                throw $exception;
            }

            return new CreateDeliveryRequestResult($existing->semanticFingerprint === $request->semanticFingerprint ? CreateDeliveryRequestStatus::Existing : CreateDeliveryRequestStatus::IdempotencyConflict, $existing);
        }
        ProcessSalesDocumentDeliveryJob::dispatch($outboxId->toString())->afterCommit();
        Log::info('Sales document delivery request queued.', ['request_id' => $request->id->toString(), 'outbox_id' => $outboxId->toString()]);

        return new CreateDeliveryRequestResult(CreateDeliveryRequestStatus::Created, $request);
    }

    public function find(AdministrationId $administrationId, DeliveryRequestId $requestId): ?DeliveryRequest
    {
        $row = DB::table('sales_document_delivery_requests')->where('administration_id', $administrationId->toString())->where('id', $requestId->toString())->first();

        return $row === null ? null : $this->hydrateRequest($row);
    }

    public function claim(DeliveryOutboxMessageId $outboxId): ?ClaimedDelivery
    {
        return DB::transaction(function () use ($outboxId): ?ClaimedDelivery {
            $outbox = DB::table('sales_document_delivery_outbox')->where('id', $outboxId->toString())->lockForUpdate()->first();
            if ($outbox === null || $outbox->status === DeliveryOutboxStatus::Processed->value || $outbox->status === DeliveryOutboxStatus::Blocked->value) {
                return null;
            }
            $requestRow = DB::table('sales_document_delivery_requests')->where('administration_id', $outbox->administration_id)->where('id', $outbox->delivery_request_id)->lockForUpdate()->first();
            if ($requestRow === null || $requestRow->status === DeliveryRequestStatus::AcceptedByTransport->value || $requestRow->status === DeliveryRequestStatus::OutcomeUnknown->value) {
                return null;
            }
            if ($outbox->status === DeliveryOutboxStatus::Processing->value) {
                if ($outbox->lease_expires_at === null || new DateTimeImmutable($outbox->lease_expires_at) > new DateTimeImmutable) {
                    return null;
                }

                return null;
            }
            if (new DateTimeImmutable($outbox->available_at) > new DateTimeImmutable || (int) $outbox->processing_attempts >= self::MAX_ATTEMPTS) {
                return null;
            }
            $number = (int) DB::table('sales_document_delivery_attempts')->where('administration_id', $outbox->administration_id)->where('delivery_request_id', $outbox->delivery_request_id)->max('attempt_number') + 1;
            $attemptId = $this->identities->attemptId();
            $now = now();
            DB::table('sales_document_delivery_attempts')->insert([
                'id' => $attemptId->toString(), 'administration_id' => $outbox->administration_id, 'delivery_request_id' => $outbox->delivery_request_id,
                'attempt_number' => $number, 'artifact_id' => $requestRow->artifact_id, 'result' => DeliveryAttemptResult::Attempting->value,
                'started_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('sales_document_delivery_requests')->where('id', $requestRow->id)->update(['status' => DeliveryRequestStatus::Attempting->value, 'updated_at' => $now]);
            DB::table('sales_document_delivery_outbox')->where('id', $outbox->id)->update(['status' => DeliveryOutboxStatus::Processing->value, 'processing_attempts' => DB::raw('processing_attempts + 1'), 'claimed_at' => $now, 'lease_expires_at' => now()->addSeconds((int) config('delivery_operations.processing_lease_seconds')), 'updated_at' => $now]);
            $request = $this->hydrateRequest($requestRow);
            $attempt = new DeliveryAttempt($attemptId, $request->administrationId, $request->id, $number, $request->artifactId, DeliveryAttemptResult::Attempting, new DateTimeImmutable($now->toISOString()));

            return new ClaimedDelivery($outboxId, $request, $attempt);
        });
    }

    public function complete(ClaimedDelivery $delivery, DocumentMailTransportResult $result): void
    {
        DB::transaction(function () use ($delivery, $result): void {
            $attempt = DB::table('sales_document_delivery_attempts')->where('id', $delivery->attempt->id->toString())->lockForUpdate()->first();
            if ($attempt === null || $attempt->result !== DeliveryAttemptResult::Attempting->value) {
                return;
            }
            $now = now();
            DB::table('sales_document_delivery_attempts')->where('id', $attempt->id)->update(['result' => $result->result->value, 'completed_at' => $now, 'transport' => $this->transport->identifier(), 'external_message_id' => $result->externalMessageId, 'error_category' => $result->errorCategory, 'retryable' => $result->retryable, 'updated_at' => $now]);
            $requestStatus = match ($result->result) {
                DeliveryAttemptResult::AcceptedByTransport => DeliveryRequestStatus::AcceptedByTransport,
                DeliveryAttemptResult::OutcomeUnknown => DeliveryRequestStatus::OutcomeUnknown,
                default => DeliveryRequestStatus::Failed,
            };
            DB::table('sales_document_delivery_requests')->where('id', $delivery->request->id->toString())->update(['status' => $requestStatus->value, 'updated_at' => $now]);
            $retry = $result->retryable && $delivery->attempt->number < self::MAX_ATTEMPTS;
            DB::table('sales_document_delivery_outbox')->where('id', $delivery->outboxId->toString())->update(['status' => $retry ? DeliveryOutboxStatus::Available->value : ($result->result === DeliveryAttemptResult::AcceptedByTransport ? DeliveryOutboxStatus::Processed->value : DeliveryOutboxStatus::Blocked->value), 'available_at' => $retry ? now()->addSeconds(30 * $delivery->attempt->number) : $now, 'processed_at' => $result->result === DeliveryAttemptResult::AcceptedByTransport ? $now : null, 'claimed_at' => null, 'lease_expires_at' => null, 'updated_at' => $now]);
            if ($retry) {
                ProcessSalesDocumentDeliveryJob::dispatch($delivery->outboxId->toString())->delay(now()->addSeconds(30 * $delivery->attempt->number))->afterCommit();
                Log::warning('Sales document delivery retry scheduled.', ['request_id' => $delivery->request->id->toString(), 'attempt' => $delivery->attempt->number]);
            }
        });
    }

    public function markTransportStarted(ClaimedDelivery $delivery): bool
    {
        return DB::transaction(function () use ($delivery): bool {
            $outbox = DB::table('sales_document_delivery_outbox')->where('id', $delivery->outboxId->toString())->lockForUpdate()->first();
            $attempt = DB::table('sales_document_delivery_attempts')->where('id', $delivery->attempt->id->toString())->lockForUpdate()->first();
            if ($outbox?->status !== DeliveryOutboxStatus::Processing->value || $attempt?->result !== DeliveryAttemptResult::Attempting->value || $attempt->transport_started_at !== null) {
                return false;
            }
            DB::table('sales_document_delivery_attempts')->where('id', $attempt->id)->update(['transport_started_at' => now(), 'updated_at' => now()]);

            return true;
        });
    }

    public function recoverStalePreSend(): int
    {
        $recovered = DB::transaction(function (): array {
            $recovered = [];
            $outboxes = DB::table('sales_document_delivery_outbox')->where('status', DeliveryOutboxStatus::Processing->value)->where('lease_expires_at', '<=', now())->lockForUpdate()->get();
            foreach ($outboxes as $outbox) {
                $attempt = DB::table('sales_document_delivery_attempts')->where('administration_id', $outbox->administration_id)->where('delivery_request_id', $outbox->delivery_request_id)->where('result', DeliveryAttemptResult::Attempting->value)->lockForUpdate()->first();
                if ($attempt === null) {
                    continue;
                }
                if ($attempt->transport_started_at !== null) {
                    $this->unknownRows($outbox->administration_id, $outbox->delivery_request_id, 'expired_lease_after_transport_start');
                    DB::table('sales_document_delivery_outbox')->where('id', $outbox->id)->update(['status' => DeliveryOutboxStatus::Blocked->value, 'lease_expires_at' => null, 'updated_at' => now()]);

                    continue;
                }
                DB::table('sales_document_delivery_attempts')->where('id', $attempt->id)->update(['result' => DeliveryAttemptResult::FailedTransport->value, 'completed_at' => now(), 'error_category' => 'pre_send_worker_crash', 'retryable' => true, 'updated_at' => now()]);
                DB::table('sales_document_delivery_requests')->where('id', $outbox->delivery_request_id)->update(['status' => DeliveryRequestStatus::Failed->value, 'updated_at' => now()]);
                DB::table('sales_document_delivery_outbox')->where('id', $outbox->id)->update(['status' => DeliveryOutboxStatus::Available->value, 'available_at' => now(), 'claimed_at' => null, 'lease_expires_at' => null, 'updated_at' => now()]);
                $recovered[] = $outbox->id;
            }

            return $recovered;
        }, 5);
        foreach ($recovered as $id) {
            ProcessSalesDocumentDeliveryJob::dispatch($id)->afterCommit();
        }

        return count($recovered);
    }

    public function markOutcomeUnknown(DeliveryOutboxMessageId $outboxId, string $category): void
    {
        DB::transaction(function () use ($outboxId, $category): void {
            $outbox = DB::table('sales_document_delivery_outbox')->where('id', $outboxId->toString())->lockForUpdate()->first();
            if ($outbox === null || $outbox->status !== DeliveryOutboxStatus::Processing->value) {
                return;
            }
            $attempt = DB::table('sales_document_delivery_attempts')->where('administration_id', $outbox->administration_id)->where('delivery_request_id', $outbox->delivery_request_id)->where('result', DeliveryAttemptResult::Attempting->value)->lockForUpdate()->first();
            if ($attempt !== null && $attempt->transport_started_at === null) {
                DB::table('sales_document_delivery_attempts')->where('id', $attempt->id)->update(['result' => DeliveryAttemptResult::FailedTransport->value, 'completed_at' => now(), 'error_category' => 'pre_send_worker_failure', 'retryable' => true, 'updated_at' => now()]);
                DB::table('sales_document_delivery_requests')->where('id', $outbox->delivery_request_id)->update(['status' => DeliveryRequestStatus::Failed->value, 'updated_at' => now()]);
                DB::table('sales_document_delivery_outbox')->where('id', $outbox->id)->update(['status' => DeliveryOutboxStatus::Available->value, 'available_at' => now(), 'claimed_at' => null, 'lease_expires_at' => null, 'updated_at' => now()]);

                return;
            }
            $this->unknownRows($outbox->administration_id, $outbox->delivery_request_id, $category);
            DB::table('sales_document_delivery_outbox')->where('id', $outbox->id)->update(['status' => DeliveryOutboxStatus::Blocked->value, 'lease_expires_at' => null, 'updated_at' => now()]);
        });
    }

    public function read(AdministrationId $administrationId, SalesDocumentSource $source): ?SalesDocumentDeliverySource
    {
        [$table, $number, $relation] = match ($source->type) {
            SalesDocumentType::Quotation => ['quotations', 'quotation_number', 'customer_relation_id_snapshot'],
            SalesDocumentType::SalesInvoice => ['sales_invoices', 'sales_invoice_number', 'customer_relation_id_snapshot'],
            SalesDocumentType::SalesCreditInvoice => ['sales_credit_invoices', 'sales_credit_invoice_number', 'customer_relation_id_snapshot'],
        };
        $row = DB::table($table)->where('administration_id', $administrationId->toString())->where('id', $source->id)->first();
        if ($row === null) {
            return null;
        }

        $hasDocumentAddress = $source->type !== SalesDocumentType::Quotation || $row->quotation_address_id_snapshot !== null;

        return new SalesDocumentDeliverySource($source, (string) $row->{$number}, new RelationId(new Uuid($row->{$relation})), (string) $row->customer_name_snapshot, (string) $row->status, $hasDocumentAddress);
    }

    public function history(AdministrationId $administrationId, SalesDocumentSource $source): SalesDocumentDeliveryHistory
    {
        $column = match ($source->type) {
            SalesDocumentType::Quotation => 'quotation_id', SalesDocumentType::SalesInvoice => 'sales_invoice_id', SalesDocumentType::SalesCreditInvoice => 'sales_credit_invoice_id'
        };
        $requests = DB::table('sales_document_delivery_requests')->where('administration_id', $administrationId->toString())->where('document_type', $source->type->value)->where($column, $source->id)->orderBy('requested_at')->get()->map(fn ($row) => (array) $row)->all();
        $ids = array_column($requests, 'id');
        $attempts = $ids === [] ? [] : DB::table('sales_document_delivery_attempts')->where('administration_id', $administrationId->toString())->whereIn('delivery_request_id', $ids)->orderBy('started_at')->get()->map(fn ($row) => (array) $row)->all();

        $attemptIds = array_column($attempts, 'id');
        $resolutions = $attemptIds === [] ? [] : DB::table('sales_document_delivery_outcome_resolutions')->where('administration_id', $administrationId->toString())->whereIn('delivery_attempt_id', $attemptIds)->get()->mapWithKeys(fn ($row): array => [$row->delivery_attempt_id => (array) $row])->all();

        return new SalesDocumentDeliveryHistory($requests, $attempts, $resolutions);
    }

    public function pending(): array
    {
        $accepted = DB::table('sales_document_delivery_requests as requests')
            ->join('quotations', function ($join): void {
                $join->on('quotations.id', '=', 'requests.quotation_id')->on('quotations.administration_id', '=', 'requests.administration_id');
            })->where('requests.document_type', SalesDocumentType::Quotation->value)
            ->where('requests.status', DeliveryRequestStatus::AcceptedByTransport->value)->where('quotations.status', 'draft')
            ->select('requests.administration_id', 'requests.quotation_id')->distinct()->get();
        $handled = DB::table('sales_document_delivery_outcome_resolutions as resolutions')
            ->join('sales_document_delivery_requests as requests', 'requests.id', '=', 'resolutions.delivery_request_id')
            ->join('quotations', function ($join): void {
                $join->on('quotations.id', '=', 'requests.quotation_id')->on('quotations.administration_id', '=', 'requests.administration_id');
            })->where('requests.document_type', SalesDocumentType::Quotation->value)
            ->where('resolutions.resolution_type', 'handled_externally')->where('quotations.status', 'draft')
            ->select('requests.administration_id', 'requests.quotation_id')->distinct()->get();

        return $accepted->concat($handled)->unique(fn ($row): string => $row->administration_id.':'.$row->quotation_id)
            ->map(fn ($row): QuotationDeliveryLifecycleCandidate => new QuotationDeliveryLifecycleCandidate(new AdministrationId(new Uuid($row->administration_id)), new QuotationId(new Uuid($row->quotation_id))))
            ->values()->all();
    }

    private function unknownRows(string $administrationId, string $requestId, string $category): void
    {
        DB::table('sales_document_delivery_attempts')->where('administration_id', $administrationId)->where('delivery_request_id', $requestId)->where('result', DeliveryAttemptResult::Attempting->value)->update(['result' => DeliveryAttemptResult::OutcomeUnknown->value, 'completed_at' => now(), 'error_category' => $category, 'retryable' => false, 'updated_at' => now()]);
        DB::table('sales_document_delivery_requests')->where('administration_id', $administrationId)->where('id', $requestId)->update(['status' => DeliveryRequestStatus::OutcomeUnknown->value, 'updated_at' => now()]);
    }

    /** @return array<string, mixed> */
    private function requestRow(DeliveryRequest $request): array
    {
        $sources = ['quotation_id' => null, 'sales_invoice_id' => null, 'sales_credit_invoice_id' => null];
        $sources[match ($request->documentType) {
            SalesDocumentType::Quotation => 'quotation_id', SalesDocumentType::SalesInvoice => 'sales_invoice_id', SalesDocumentType::SalesCreditInvoice => 'sales_credit_invoice_id'
        }] = $request->sourceId;

        return $sources + ['id' => $request->id->toString(), 'administration_id' => $request->administrationId->toString(), 'document_type' => $request->documentType->value, 'artifact_id' => $request->artifactId->toString(), 'recipient_email' => $request->recipientEmail, 'recipient_name' => $request->recipientName, 'recipient_contact_id' => $request->recipientContactId, 'recipient_source' => $request->recipientSource, 'from_name' => $request->fromName, 'from_email' => $request->fromEmail, 'reply_to' => $request->replyTo, 'subject' => $request->subject, 'body' => $request->body, 'template_version' => $request->templateVersion, 'semantic_fingerprint' => $request->semanticFingerprint, 'initiated_by' => $request->initiatedBy->toString(), 'status' => $request->status->value, 'requested_at' => $request->createdAt, 'created_at' => $request->createdAt, 'updated_at' => $request->createdAt];
    }

    private function hydrateRequest(object $row): DeliveryRequest
    {
        $type = SalesDocumentType::from($row->document_type);
        $source = match ($type) {
            SalesDocumentType::Quotation => $row->quotation_id, SalesDocumentType::SalesInvoice => $row->sales_invoice_id, SalesDocumentType::SalesCreditInvoice => $row->sales_credit_invoice_id
        };

        return new DeliveryRequest(new DeliveryRequestId(new Uuid($row->id)), new AdministrationId(new Uuid($row->administration_id)), $type, $source, new ArtifactId(new Uuid($row->artifact_id)), $row->recipient_email, $row->recipient_name, $row->recipient_contact_id, $row->recipient_source, $row->from_name, $row->from_email, $row->reply_to, $row->subject, $row->body, $row->template_version, $row->semantic_fingerprint, new UserId(new Uuid($row->initiated_by)), DeliveryRequestStatus::from($row->status), new DateTimeImmutable($row->requested_at));
    }
}
