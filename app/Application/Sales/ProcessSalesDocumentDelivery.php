<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\Enums\DeliveryAttemptResult;
use App\Domain\Sales\ValueObjects\DeliveryOutboxMessageId;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class ProcessSalesDocumentDelivery
{
    public function __construct(private DeliveryOutboxStore $outbox, private ReadDocumentArtifact $artifacts, private DocumentMailTransport $transport) {}

    public function execute(DeliveryOutboxMessageId $outboxId): void
    {
        $claimed = $this->outbox->claim($outboxId);
        if ($claimed === null) {
            return;
        }
        Log::info('Sales document delivery processing started.', ['request_id' => $claimed->request->id->toString(), 'attempt' => $claimed->attempt->number]);
        $read = $this->artifacts->execute($claimed->request->administrationId, $claimed->request->artifactId);
        if (! $read->integrityValid || $read->artifact === null || $read->bytes === null) {
            $this->outbox->complete($claimed, new DocumentMailTransportResult(DeliveryAttemptResult::FailedValidation, false, null, 'artifact_integrity'));
            Log::warning('Sales document delivery failed validation.', ['request_id' => $claimed->request->id->toString(), 'category' => 'artifact_integrity']);

            return;
        }
        $message = new DocumentMailMessage($claimed->request->recipientEmail, $claimed->request->recipientName, $claimed->request->fromEmail, $claimed->request->fromName, $claimed->request->replyTo, $claimed->request->subject, $claimed->request->body, $read->bytes, $read->artifact->filename);
        try {
            $result = $this->transport->send($message);
            $this->outbox->complete($claimed, $result);
            Log::info('Sales document delivery transport result recorded.', ['request_id' => $claimed->request->id->toString(), 'result' => $result->result->value, 'category' => $result->errorCategory]);
        } catch (Throwable) {
            $this->outbox->complete($claimed, DocumentMailTransportResult::unknown('transport_exception_after_attempt'));
            Log::critical('Sales document delivery outcome is unknown.', ['request_id' => $claimed->request->id->toString(), 'category' => 'transport_exception_after_attempt']);
        }
    }
}
