<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Entities\SalesCreditInvoice;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;
use Closure;
use DomainException;
use InvalidArgumentException;

final readonly class SalesCreditInvoiceMutationService
{
    public function __construct(private SalesCreditInvoiceReadRepository $reader, private SalesCreditInvoiceUpdater $updater, private TransactionManager $transactions) {}

    /** @param Closure(SalesCreditInvoice): (?SalesCreditInvoiceWriteResult) $mutation */
    public function mutate(AdministrationId $administrationId, SalesCreditInvoiceId $id, Closure $mutation): SalesCreditInvoiceWriteResult
    {
        try {
            return $this->transactions->run(function () use ($administrationId, $id, $mutation): SalesCreditInvoiceWriteResult {
                $invoice = $this->reader->findForAdministration($administrationId, $id);
                if ($invoice === null) {
                    return SalesCreditInvoiceWriteResult::NotFound;
                }
                try {
                    $failure = $mutation($invoice);
                } catch (DomainException|InvalidArgumentException) {
                    return SalesCreditInvoiceWriteResult::InvalidState;
                }
                if ($failure instanceof SalesCreditInvoiceWriteResult && $failure !== SalesCreditInvoiceWriteResult::Success) {
                    return $failure;
                }
                $result = $this->updater->update($administrationId, $invoice);
                if ($result !== SalesCreditInvoiceWriteResult::Success) {
                    throw new SalesCreditPersistenceFailure($result);
                }

                return SalesCreditInvoiceWriteResult::Success;
            });
        } catch (SalesCreditPersistenceFailure $failure) {
            return $failure->result;
        }
    }
}
