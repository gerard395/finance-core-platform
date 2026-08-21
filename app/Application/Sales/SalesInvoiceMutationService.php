<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Entities\SalesInvoice;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use Closure;
use DomainException;
use InvalidArgumentException;

final readonly class SalesInvoiceMutationService
{
    public function __construct(private SalesInvoiceReadRepository $reader, private SalesInvoiceUpdater $updater, private TransactionManager $transactions) {}

    /** @param Closure(SalesInvoice): (?SalesInvoiceWriteResult) $mutation */
    public function mutate(AdministrationId $administrationId, SalesInvoiceId $invoiceId, Closure $mutation): SalesInvoiceWriteResult
    {
        try {
            return $this->transactions->run(function () use ($administrationId, $invoiceId, $mutation): SalesInvoiceWriteResult {
                $invoice = $this->reader->findForAdministration($administrationId, $invoiceId);
                if ($invoice === null) {
                    return SalesInvoiceWriteResult::NotFound;
                }
                try {
                    $failure = $mutation($invoice);
                } catch (DomainException|InvalidArgumentException) {
                    return SalesInvoiceWriteResult::InvalidState;
                }
                if ($failure instanceof SalesInvoiceWriteResult && $failure !== SalesInvoiceWriteResult::Success) {
                    return $failure;
                }

                $result = $this->updater->update($administrationId, $invoice);
                if ($result !== SalesInvoiceWriteResult::Success) {
                    throw new SalesInvoicePersistenceConflict($result);
                }

                return SalesInvoiceWriteResult::Success;
            });
        } catch (SalesInvoicePersistenceConflict $conflict) {
            return $conflict->result();
        }
    }
}
