<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Purchasing\Entities\PurchaseInvoice;
use App\Domain\Purchasing\Enums\PurchaseInvoiceStatus;

final readonly class CreatePurchaseInvoice
{
    public function __construct(private TransactionManager $transactions, private PurchaseInvoiceRepository $repository, private PurchaseInvoiceIdentityGenerator $ids, private PurchaseInvoiceAssembler $assembler) {}

    public function execute(AdministrationId $admin, PurchaseInvoiceDraftInput $input): CreatePurchaseInvoiceResult
    {
        return $this->transactions->run(function () use ($admin, $input): CreatePurchaseInvoiceResult {
            $supplier = $this->assembler->supplier($admin, $input);
            if ($supplier === null) {
                $status = $this->assembler->supplierExists($admin, $input)
                    ? CreatePurchaseInvoiceStatus::InvalidSupplier
                    : CreatePurchaseInvoiceStatus::SupplierNotFound;

                return new CreatePurchaseInvoiceResult($status);
            }
            $lines = $this->assembler->lines($admin, $input);
            if ($lines === null) {
                return new CreatePurchaseInvoiceResult(CreatePurchaseInvoiceStatus::InvalidLineReference);
            }
            $id = $this->ids->invoiceId();
            $invoice = new PurchaseInvoice($id, $input->number, $admin, $supplier, $input->currency, $input->invoiceDate, $input->receivedDate, $input->supplyDate, max($input->invoiceDate, $input->receivedDate), $input->dueDate, $input->address, PurchaseInvoiceStatus::Draft, $lines);

            return $this->repository->create($invoice) ? new CreatePurchaseInvoiceResult(CreatePurchaseInvoiceStatus::Success, $id) : new CreatePurchaseInvoiceResult(CreatePurchaseInvoiceStatus::DuplicateSupplierInvoice);
        });
    }
}
