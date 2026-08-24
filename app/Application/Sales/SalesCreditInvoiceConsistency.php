<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\Enums\TaxPostingType;
use App\Domain\Fiscal\Enums\TaxSourceDocumentType;
use App\Domain\Sales\Entities\SalesCreditInvoice;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceLineId;

final readonly class SalesCreditInvoiceConsistency
{
    public function matches(SalesCreditInvoice $credit, SalesCreditSource $source): bool
    {
        $invoice = $source->invoice();
        if ($source->status() !== SalesCreditSourceStatus::Success || $invoice === null || ! $credit->sourceInvoiceId()->equals($invoice->id()) || ! $credit->administrationId()->equals($invoice->administrationId()) || ! $credit->customerId()->equals($invoice->customerId()) || ! $credit->currency()->equals($invoice->currency()) || count($credit->lines()) !== count($invoice->lines())) {
            return false;
        }
        $postings = [];
        foreach ($source->originalTaxPostings() as $posting) {
            if ($posting->type() !== TaxPostingType::Original || $posting->direction() !== TaxPostingDirection::Output || $posting->sourceDocumentType() !== TaxSourceDocumentType::SalesInvoice) {
                return false;
            }
            $postings[$posting->sourceLineId()->toString()] = $posting;
        }
        foreach ($invoice->lines() as $sourceLine) {
            $line = $credit->line(new SalesCreditInvoiceLineId($sourceLine->id()->uuid()));
            $posting = $postings[$sourceLine->id()->toString()] ?? null;
            if ($line === null || $posting === null || ! $line->description()->equals($sourceLine->description()) || ! $line->quantity()->equals($sourceLine->quantity()) || ! $line->unitPrice()->equals($sourceLine->unitPrice()) || ! $line->lineTotal()->equals($posting->taxableBase())) {
                return false;
            }
        }

        return count($postings) === count($invoice->lines());
    }
}
