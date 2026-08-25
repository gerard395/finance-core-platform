<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Purchasing\Enums\PurchaseInvoiceStatus;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;

final readonly class UpdateDraftPurchaseInvoice
{
    public function __construct(private TransactionManager $transactions, private PurchaseInvoiceRepository $repository, private PurchaseInvoiceAssembler $assembler) {}

    public function execute(AdministrationId $admin, PurchaseInvoiceId $id, PurchaseInvoiceDraftInput $input): UpdateDraftPurchaseInvoiceResult
    {
        return $this->transactions->run(function () use ($admin, $id, $input): UpdateDraftPurchaseInvoiceResult {
            $invoice = $this->repository->findForUpdate($admin, $id);
            if ($invoice === null) {
                return UpdateDraftPurchaseInvoiceResult::NotFound;
            }
            if ($invoice->status() !== PurchaseInvoiceStatus::Draft || ! $invoice->supplierId()->equals($input->supplierId)) {
                return UpdateDraftPurchaseInvoiceResult::InvalidState;
            }
            $lines = $this->assembler->lines($admin, $input);
            if ($lines === null) {
                return UpdateDraftPurchaseInvoiceResult::InvalidLineReference;
            }
            $invoice->replaceDraft($input->number, $input->invoiceDate, $input->receivedDate, $input->supplyDate, $input->dueDate, $input->address, $lines);

            return $this->repository->save($invoice) ? UpdateDraftPurchaseInvoiceResult::Saved : UpdateDraftPurchaseInvoiceResult::DuplicateSupplierInvoice;
        });
    }
}
