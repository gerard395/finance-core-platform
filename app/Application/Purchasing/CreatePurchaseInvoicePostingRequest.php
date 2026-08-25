<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Accounting\Entities\JournalEntryLine;
use App\Domain\Accounting\Requests\PostingRequest;
use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\JournalEntryReference;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Purchasing\Entities\PurchaseInvoice;
use App\Domain\Purchasing\Enums\PurchaseInvoiceStatus;
use App\Domain\Shared\Finance\Money;
use DomainException;

final class CreatePurchaseInvoicePostingRequest
{
    public function execute(
        PurchaseInvoice $invoice,
        JournalId $purchaseJournalId,
        LedgerAccountId $creditorAccountId,
        LedgerAccountId $expenseAccountId,
        JournalEntryLineId $expenseLineId,
        JournalEntryLineId $creditorLineId,
        PostingDate $postingDate,
        JournalEntryReference $reference,
    ): PostingRequest {
        if (! in_array($invoice->status(), [
            PurchaseInvoiceStatus::Finalized,
            PurchaseInvoiceStatus::Posted,
        ], true)) {
            throw new DomainException('A purchase invoice must be at least finalized before a posting request can be created.');
        }

        $total = Money::zero($invoice->currency());

        foreach ($invoice->lines() as $line) {
            $total = $total->add($line->lineTotal());
        }

        $description = 'Purchase invoice '.$invoice->number()->value();

        return new PostingRequest(
            $invoice->administrationId(),
            $purchaseJournalId,
            $postingDate,
            $reference,
            [
                new JournalEntryLine($expenseLineId, $expenseAccountId, $total, null, $description),
                new JournalEntryLine($creditorLineId, $creditorAccountId, null, $total, $description),
            ],
        );
    }
}
