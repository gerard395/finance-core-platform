<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Accounting\Entities\JournalEntryLine;
use App\Domain\Accounting\Requests\PostingRequest;
use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\JournalEntryReference;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Sales\Entities\SalesInvoice;
use App\Domain\Sales\Enums\SalesInvoiceStatus;
use App\Domain\Shared\Finance\Money;
use DomainException;

final class CreateSalesInvoicePostingRequest
{
    public function execute(
        SalesInvoice $invoice,
        JournalId $salesJournalId,
        LedgerAccountId $debtorAccountId,
        LedgerAccountId $revenueAccountId,
        JournalEntryLineId $debtorLineId,
        JournalEntryLineId $revenueLineId,
        PostingDate $postingDate,
        JournalEntryReference $reference,
    ): PostingRequest {
        $total = Money::zero($invoice->currency());

        foreach ($invoice->lines() as $line) {
            $total = $total->add($line->lineTotal());
        }

        $description = 'Sales invoice '.$invoice->number()->value();

        return $this->executeForFinancialLines(
            $invoice,
            $salesJournalId,
            [
                new JournalEntryLine($debtorLineId, $debtorAccountId, $total, null, $description),
                new JournalEntryLine($revenueLineId, $revenueAccountId, null, $total, $description),
            ],
            $postingDate,
            $reference,
        );
    }

    /** @param list<JournalEntryLine> $lines */
    public function executeForFinancialLines(
        SalesInvoice $invoice,
        JournalId $salesJournalId,
        array $lines,
        PostingDate $postingDate,
        JournalEntryReference $reference,
    ): PostingRequest {
        if (! in_array($invoice->status(), [
            SalesInvoiceStatus::Finalized,
            SalesInvoiceStatus::Posted,
            SalesInvoiceStatus::Paid,
        ], true)) {
            throw new DomainException('A sales invoice must be at least finalized before a posting request can be created.');
        }

        return new PostingRequest(
            $invoice->administrationId(),
            $salesJournalId,
            $postingDate,
            $reference,
            $lines,
        );
    }
}
