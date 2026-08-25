<?php

declare(strict_types=1);

namespace App\Infrastructure\Sales;

use App\Application\Sales\DeliveryInfrastructureReadiness;
use App\Application\Sales\DeliveryInfrastructureReadinessStatus;
use App\Application\Sales\DeliveryOutcomeResolutionStatus;
use App\Application\Sales\DeliveryOutcomeResolutionStore;
use App\Application\Sales\DeliveryWorkerHeartbeatStore;
use App\Application\Sales\SalesDocumentDeliveryInfrastructureReadiness;
use App\Domain\Sales\Entities\DeliveryOutcomeResolution;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class DatabaseDeliveryOperations implements DeliveryOutcomeResolutionStore, DeliveryWorkerHeartbeatStore, SalesDocumentDeliveryInfrastructureReadiness
{
    private const HEARTBEAT_ID = 'e4c26017-66d1-4a44-b45b-2d7c4af064cd';

    private const CAPABILITY = 'sales-document-delivery';

    public function beat(string $workerIdentity, ?string $release = null): void
    {
        $existing = DB::table('delivery_worker_heartbeats')->where('id', self::HEARTBEAT_ID)->first();
        if ($existing !== null && now()->diffInSeconds($existing->last_seen_at, true) < (int) config('delivery_operations.heartbeat_interval_seconds')) {
            return;
        }
        DB::table('delivery_worker_heartbeats')->upsert([[
            'id' => self::HEARTBEAT_ID, 'capability' => self::CAPABILITY, 'queue_name' => (string) config('delivery_operations.queue_name'),
            'worker_identity' => mb_substr($workerIdentity, 0, 128), 'release' => $release === null ? null : mb_substr($release, 0, 128),
            'started_at' => $existing?->started_at ?? now(), 'last_seen_at' => now(), 'created_at' => $existing?->created_at ?? now(), 'updated_at' => now(),
        ]], ['id'], ['queue_name', 'worker_identity', 'release', 'last_seen_at', 'updated_at']);
    }

    public function check(): DeliveryInfrastructureReadiness
    {
        $backend = (string) config('queue.default');
        $queue = (string) config('delivery_operations.queue_name');
        $counters = $this->counters();
        if ($backend !== 'database' || $backend !== (string) config('delivery_operations.queue_connection') || $queue !== self::CAPABILITY) {
            return new DeliveryInfrastructureReadiness(DeliveryInfrastructureReadinessStatus::Misconfigured, $backend, $queue, null, $counters);
        }
        try {
            if (! Schema::hasTable('jobs') || ! Schema::hasTable('failed_jobs')) {
                throw new \RuntimeException('Queue schema unavailable.');
            }
            DB::select('select 1');
        } catch (Throwable) {
            return new DeliveryInfrastructureReadiness(DeliveryInfrastructureReadinessStatus::QueueUnavailable, $backend, $queue, null, $counters);
        }
        $heartbeat = DB::table('delivery_worker_heartbeats')->where('capability', self::CAPABILITY)->where('queue_name', $queue)->first();
        $age = $heartbeat === null ? null : (int) now()->diffInSeconds($heartbeat->last_seen_at, true);
        if ($age === null || $age > (int) config('delivery_operations.heartbeat_stale_seconds')) {
            return new DeliveryInfrastructureReadiness(DeliveryInfrastructureReadinessStatus::WorkerUnavailable, $backend, $queue, $age, $counters);
        }
        $mailer = (string) config('mail.default');
        if ($mailer === '' || (app()->environment('production') && in_array($mailer, ['log', 'array'], true))) {
            return new DeliveryInfrastructureReadiness(DeliveryInfrastructureReadinessStatus::MailTransportUnavailable, $backend, $queue, $age, $counters);
        }
        $probe = '.operations/readiness-'.bin2hex(random_bytes(8));
        try {
            Storage::disk('sales_documents')->put($probe, 'ready');
            $valid = Storage::disk('sales_documents')->get($probe) === 'ready';
            Storage::disk('sales_documents')->delete($probe);
            if (! $valid) {
                throw new \RuntimeException('Storage probe mismatch.');
            }
        } catch (Throwable) {
            return new DeliveryInfrastructureReadiness(DeliveryInfrastructureReadinessStatus::ArtifactStorageUnavailable, $backend, $queue, $age, $counters);
        }

        return new DeliveryInfrastructureReadiness(DeliveryInfrastructureReadinessStatus::Ready, $backend, $queue, $age, $counters);
    }

    public function appendForUnknownAttempt(DeliveryOutcomeResolution $resolution): DeliveryOutcomeResolutionStatus
    {
        return DB::transaction(function () use ($resolution): DeliveryOutcomeResolutionStatus {
            $attempt = DB::table('sales_document_delivery_attempts')->where('id', $resolution->attemptId->toString())->lockForUpdate()->first();
            if ($attempt === null || $attempt->administration_id !== $resolution->administrationId->toString() || $attempt->delivery_request_id !== $resolution->requestId->toString()) {
                return DeliveryOutcomeResolutionStatus::NotFound;
            }
            if ($attempt->result !== 'outcome_unknown') {
                return DeliveryOutcomeResolutionStatus::InvalidAttemptStatus;
            }
            if (DB::table('sales_document_delivery_outcome_resolutions')->where('delivery_attempt_id', $attempt->id)->exists()) {
                return DeliveryOutcomeResolutionStatus::AlreadyResolved;
            }
            try {
                DB::table('sales_document_delivery_outcome_resolutions')->insert([
                    'id' => $resolution->id->toString(), 'administration_id' => $resolution->administrationId->toString(), 'delivery_request_id' => $resolution->requestId->toString(),
                    'delivery_attempt_id' => $resolution->attemptId->toString(), 'resolution_type' => $resolution->type->value, 'resolved_by' => $resolution->resolvedBy->toString(),
                    'resolved_at' => $resolution->resolvedAt, 'reason' => $resolution->reason, 'created_at' => $resolution->resolvedAt, 'updated_at' => $resolution->resolvedAt,
                ]);
            } catch (QueryException) {
                return DeliveryOutcomeResolutionStatus::AlreadyResolved;
            }

            return DeliveryOutcomeResolutionStatus::Resolved;
        });
    }

    /** @return array<string, int> */
    private function counters(): array
    {
        if (! Schema::hasTable('sales_document_delivery_outbox')) {
            return [];
        }

        return [
            'pending_requests' => DB::table('sales_document_delivery_requests')->whereIn('status', ['requested', 'prepared', 'attempting'])->count(),
            'pending_outbox' => DB::table('sales_document_delivery_outbox')->where('status', 'available')->count(),
            'processing' => DB::table('sales_document_delivery_outbox')->where('status', 'processing')->count(),
            'stale_processing' => DB::table('sales_document_delivery_outbox')->where('status', 'processing')->where('lease_expires_at', '<=', now())->count(),
            'retryable_failures' => DB::table('sales_document_delivery_attempts')->where('retryable', true)->count(),
            'outcome_unknown' => DB::table('sales_document_delivery_attempts')->where('result', 'outcome_unknown')->count(),
            'failed_jobs' => DB::table('failed_jobs')->count(),
        ];
    }
}
