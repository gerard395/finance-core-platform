<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Entities\SalesCreditInvoice;
use App\Domain\Sales\Entities\SalesCreditInvoiceLine;
use App\Domain\Sales\Enums\SalesCreditInvoiceStatus;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceLineId;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceNumber;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use DateTimeImmutable;
use RuntimeException;

final readonly class CreateSalesCreditInvoiceFromInvoice
{
    public function __construct(private SalesCreditSourceReader $sources, private SalesNumberAllocator $numbers, private SalesCreditInvoiceIdentityGenerator $identities, private SalesCreditInvoiceCreator $credits, private SalesCreditInvoiceConsistency $consistency, private TransactionManager $transactions) {}

    public function execute(AdministrationId $administrationId, SalesInvoiceId $sourceInvoiceId, DateTimeImmutable $creditDate): SalesCreditInvoiceWriteResult
    {
        try {
            return $this->transactions->run(function () use ($administrationId, $sourceInvoiceId, $creditDate): SalesCreditInvoiceWriteResult {
                $source = $this->sources->read($administrationId, $sourceInvoiceId);
                $failure = self::sourceFailure($source->status());
                if ($failure !== null) {
                    return $failure;
                }
                $invoice = $source->invoice();
                $customer = $invoice?->customerSnapshot();
                $address = $invoice?->invoiceAddressSnapshot();
                if ($invoice === null || $customer === null || $address === null) {
                    return SalesCreditInvoiceWriteResult::ReversalSourceInvalid;
                }
                $allocation = $this->numbers->next($administrationId, SalesNumberType::SalesCreditInvoice);
                if ($allocation->status() === SalesNumberAllocationStatus::SequenceMissing) {
                    return SalesCreditInvoiceWriteResult::SequenceMissing;
                }
                if ($allocation->status() === SalesNumberAllocationStatus::SequenceInactive) {
                    return SalesCreditInvoiceWriteResult::SequenceInactive;
                }
                $number = $allocation->number();
                if (! $number instanceof SalesCreditInvoiceNumber) {
                    return SalesCreditInvoiceWriteResult::SequenceMissing;
                }
                $credit = new SalesCreditInvoice($this->identities->creditInvoiceId(), $number, $administrationId, $invoice->customerId(), $invoice->currency(), $creditDate, $invoice->id(), SalesCreditInvoiceStatus::Draft, $customer, $address);
                foreach ($invoice->lines() as $line) {
                    $credit->addLine(new SalesCreditInvoiceLine(new SalesCreditInvoiceLineId($line->id()->uuid()), $line->description(), $line->quantity(), $line->unitPrice()));
                }
                if (! $this->consistency->matches($credit, $source)) {
                    throw new SalesCreditPersistenceFailure(SalesCreditInvoiceWriteResult::ReversalSourceInvalid);
                }
                $result = $this->credits->create($administrationId, $credit);
                if ($result !== SalesCreditInvoiceWriteResult::Success) {
                    throw new SalesCreditPersistenceFailure($result);
                }

                return SalesCreditInvoiceWriteResult::Success;
            });
        } catch (SalesCreditPersistenceFailure $failure) {
            return $failure->result;
        }
    }

    private static function sourceFailure(SalesCreditSourceStatus $status): ?SalesCreditInvoiceWriteResult
    {
        return match ($status) {
            SalesCreditSourceStatus::Success => null,
            SalesCreditSourceStatus::NotFound => SalesCreditInvoiceWriteResult::NotFound,
            SalesCreditSourceStatus::SourceNotPosted => SalesCreditInvoiceWriteResult::SourceNotPosted,
            SalesCreditSourceStatus::FinancialPostingMissing => SalesCreditInvoiceWriteResult::FinancialPostingMissing,
            SalesCreditSourceStatus::ReversalSourceMissing => SalesCreditInvoiceWriteResult::ReversalSourceMissing,
            SalesCreditSourceStatus::ReversalSourceInvalid => SalesCreditInvoiceWriteResult::ReversalSourceInvalid,
            SalesCreditSourceStatus::AlreadyCredited => SalesCreditInvoiceWriteResult::AlreadyCredited,
        };
    }
}

final class SalesCreditPersistenceFailure extends RuntimeException
{
    public function __construct(public readonly SalesCreditInvoiceWriteResult $result)
    {
        parent::__construct($result->name);
    }
}
