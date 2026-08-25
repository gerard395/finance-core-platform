<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Sales\DeliveryOutboxStore;
use App\Application\Sales\ProcessSalesDocumentDelivery;
use App\Domain\Sales\ValueObjects\DeliveryOutboxMessageId;
use App\Domain\Shared\Identity\Uuid;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class ProcessSalesDocumentDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly string $outboxMessageId)
    {
        $this->onQueue('sales-document-delivery');
    }

    public function handle(ProcessSalesDocumentDelivery $processor): void
    {
        $processor->execute(new DeliveryOutboxMessageId(new Uuid($this->outboxMessageId)));
    }

    public function failed(?Throwable $exception): void
    {
        app(DeliveryOutboxStore::class)->markOutcomeUnknown(new DeliveryOutboxMessageId(new Uuid($this->outboxMessageId)), 'unexpected_job_failure');
    }
}
