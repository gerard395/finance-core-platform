<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Sales\Entities\Quotation;
use App\Domain\Sales\Entities\QuotationLine;
use App\Domain\Sales\Enums\QuotationStatus;
use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Sales\ValueObjects\QuotationNumber;
use App\Domain\Shared\Finance\Currency;
use DateTimeImmutable;

final readonly class CreateQuotation
{
    public function __construct(
        private SalesCustomerContextReader $customers,
        private SalesNumberAllocator $numbers,
        private QuotationCreator $quotations,
        private TransactionManager $transactions,
    ) {}

    /** @param list<QuotationLine> $lines */
    public function execute(
        AdministrationId $administrationId,
        QuotationId $quotationId,
        CustomerId $customerId,
        Currency $currency,
        DateTimeImmutable $quotationDate,
        ?DateTimeImmutable $expiryDate,
        array $lines = [],
    ): QuotationWriteResult {
        try {
            return $this->transactions->run(function () use ($administrationId, $quotationId, $customerId, $currency, $quotationDate, $expiryDate, $lines): QuotationWriteResult {
                $context = $this->customers->read($administrationId, $customerId, null);
                if ($context->status() === SalesCustomerContextStatus::NotFound) {
                    return QuotationWriteResult::CustomerNotFound;
                }
                if ($context->status() === SalesCustomerContextStatus::InactiveCustomer) {
                    return QuotationWriteResult::InactiveCustomer;
                }
                $snapshot = $context->customer();
                if ($snapshot === null) {
                    return QuotationWriteResult::CustomerNotFound;
                }
                $allocation = $this->numbers->next($administrationId, SalesNumberType::Quotation);
                if ($allocation->status() === SalesNumberAllocationStatus::SequenceMissing) {
                    return QuotationWriteResult::SequenceMissing;
                }
                if ($allocation->status() === SalesNumberAllocationStatus::SequenceInactive) {
                    return QuotationWriteResult::SequenceInactive;
                }
                $number = $allocation->number();
                if (! $number instanceof QuotationNumber) {
                    return QuotationWriteResult::SequenceMissing;
                }
                $quotation = new Quotation($quotationId, $number, $administrationId, $customerId, $currency, QuotationStatus::Draft, $quotationDate, $expiryDate, $snapshot);
                foreach ($lines as $line) {
                    $quotation->addLine($line);
                }
                $result = $this->quotations->create($administrationId, $quotation);
                if ($result !== QuotationWriteResult::Success) {
                    throw new QuotationPersistenceConflict($result);
                }

                return QuotationWriteResult::Success;
            });
        } catch (QuotationPersistenceConflict $conflict) {
            return $conflict->result();
        }
    }
}
