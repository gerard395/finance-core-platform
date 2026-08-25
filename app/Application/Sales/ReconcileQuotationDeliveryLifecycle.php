<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Enums\QuotationStatus;
use App\Domain\Sales\ValueObjects\QuotationId;

final readonly class ReconcileQuotationDeliveryLifecycle
{
    public function __construct(private QuotationDetailReadRepository $quotations, private SendQuotation $send, private QuotationDeliveryLifecycleCandidates $candidates) {}

    public function execute(AdministrationId $administrationId, QuotationId $quotationId): QuotationWriteResult
    {
        $quotation = $this->quotations->find($administrationId, $quotationId);
        if ($quotation === null) {
            return QuotationWriteResult::NotFound;
        }
        if ($quotation->status() === QuotationStatus::Sent) {
            return QuotationWriteResult::Success;
        }
        if ($quotation->status() !== QuotationStatus::Draft) {
            return QuotationWriteResult::InvalidState;
        }

        return $this->send->execute($administrationId, $quotationId);
    }

    public function reconcilePending(): int
    {
        $count = 0;
        foreach ($this->candidates->pending() as $candidate) {
            if ($this->execute($candidate->administrationId, $candidate->quotationId) === QuotationWriteResult::Success) {
                $count++;
            }
        }

        return $count;
    }
}
