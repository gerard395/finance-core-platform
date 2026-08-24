<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Application\Administration\AdministrationRepository;
use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\AddressId;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Sales\Entities\SalesInvoice;
use App\Domain\Sales\Entities\SalesInvoiceLine;
use App\Domain\Sales\Enums\SalesInvoiceStatus;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceNumber;
use App\Domain\Sales\ValueObjects\SalesTaxSnapshot;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class CreateSalesInvoice
{
    public function __construct(
        private AdministrationRepository $administrations,
        private SalesCustomerContextReader $customers,
        private SalesTaxCodeResolver $taxCodes,
        private SalesInvoiceReadinessChecker $readiness,
        private SalesNumberAllocator $numbers,
        private SalesInvoiceCreator $invoices,
        private TransactionManager $transactions,
    ) {}

    /** @param list<SalesInvoiceLineInput> $lines */
    public function execute(AdministrationId $administrationId, SalesInvoiceId $invoiceId, CustomerId $customerId, AddressId $invoiceAddressId, DateTimeImmutable $invoiceDate, DateTimeImmutable $dueDate, array $lines = []): SalesInvoiceWriteResult
    {
        try {
            return $this->transactions->run(function () use ($administrationId, $invoiceId, $customerId, $invoiceAddressId, $invoiceDate, $dueDate, $lines): SalesInvoiceWriteResult {
                $administration = $this->administrations->findById($administrationId);
                if ($administration === null) {
                    return SalesInvoiceWriteResult::CustomerNotFound;
                }
                $context = $this->customers->read($administrationId, $customerId, $invoiceAddressId);
                $contextFailure = self::customerFailure($context->status());
                if ($contextFailure !== null) {
                    return $contextFailure;
                }
                $customer = $context->customer();
                $address = $context->invoiceAddress();
                if ($customer === null || $address === null) {
                    return SalesInvoiceWriteResult::MissingInvoiceAddress;
                }
                $resolvedLines = [];
                foreach ($lines as $input) {
                    if (! $input->unitPrice()->currency()->equals($administration->baseCurrency())) {
                        return SalesInvoiceWriteResult::InvalidState;
                    }
                    $resolution = $this->taxCodes->resolve($administrationId, $input->taxCodeId());
                    $failure = self::taxFailure($resolution->status());
                    if ($failure !== null) {
                        return $failure;
                    }
                    $taxCode = $resolution->taxCode();
                    if ($taxCode === null) {
                        return SalesInvoiceWriteResult::TaxCodeNotFound;
                    }
                    try {
                        $resolvedLines[] = new SalesInvoiceLine($input->id(), $input->description(), $input->quantity(), $input->unitPrice(), SalesTaxSnapshot::fromTaxCode($taxCode));
                    } catch (InvalidArgumentException) {
                        return SalesInvoiceWriteResult::TaxCalculationFailure;
                    }
                }
                $allocation = $this->numbers->next($administrationId, SalesNumberType::SalesInvoice);
                if ($allocation->status() === SalesNumberAllocationStatus::SequenceMissing) {
                    return SalesInvoiceWriteResult::SequenceMissing;
                }
                if ($allocation->status() === SalesNumberAllocationStatus::SequenceInactive) {
                    return SalesInvoiceWriteResult::SequenceInactive;
                }
                $number = $allocation->number();
                if (! $number instanceof SalesInvoiceNumber) {
                    return SalesInvoiceWriteResult::SequenceMissing;
                }
                $invoice = new SalesInvoice($invoiceId, $number, $administrationId, $customerId, $administration->baseCurrency(), $invoiceDate, $dueDate, null, SalesInvoiceStatus::Draft, $customer, $address);
                foreach ($resolvedLines as $line) {
                    $invoice->addLine($line);
                }
                if ($resolvedLines !== [] && $this->readiness->check($invoice)->status() === SalesInvoiceReadinessStatus::TaxCalculationFailed) {
                    return SalesInvoiceWriteResult::TaxCalculationFailure;
                }
                $result = $this->invoices->create($administrationId, $invoice);
                if ($result !== SalesInvoiceWriteResult::Success) {
                    throw new SalesInvoicePersistenceConflict($result);
                }

                return SalesInvoiceWriteResult::Success;
            });
        } catch (SalesInvoicePersistenceConflict $conflict) {
            return $conflict->result();
        }
    }

    private static function customerFailure(SalesCustomerContextStatus $status): ?SalesInvoiceWriteResult
    {
        return match ($status) {
            SalesCustomerContextStatus::Success => null,
            SalesCustomerContextStatus::NotFound => SalesInvoiceWriteResult::CustomerNotFound,
            SalesCustomerContextStatus::InactiveCustomer => SalesInvoiceWriteResult::InactiveCustomer,
            SalesCustomerContextStatus::MissingInvoiceAddress => SalesInvoiceWriteResult::MissingInvoiceAddress,
        };
    }

    public static function taxFailure(SalesTaxCodeResolutionStatus $status): ?SalesInvoiceWriteResult
    {
        return match ($status) {
            SalesTaxCodeResolutionStatus::Success => null,
            SalesTaxCodeResolutionStatus::NotFound => SalesInvoiceWriteResult::TaxCodeNotFound,
            SalesTaxCodeResolutionStatus::Inactive => SalesInvoiceWriteResult::TaxCodeInactive,
            SalesTaxCodeResolutionStatus::WrongDirection => SalesInvoiceWriteResult::WrongTaxDirection,
        };
    }
}
