<?php

declare(strict_types=1);

namespace App\Application\Sales;

enum DeliveryInfrastructureReadinessStatus: string
{
    case Ready = 'ready';
    case QueueUnavailable = 'queue_unavailable';
    case WorkerUnavailable = 'worker_unavailable';
    case MailTransportUnavailable = 'mail_transport_unavailable';
    case ArtifactStorageUnavailable = 'artifact_storage_unavailable';
    case Misconfigured = 'misconfigured';
}
