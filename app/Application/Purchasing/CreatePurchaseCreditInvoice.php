<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Purchasing\Entities\PurchaseCreditInvoice;
use App\Domain\Purchasing\Enums\PurchaseCreditInvoiceStatus;

final readonly class CreatePurchaseCreditInvoice
{
    public function __construct(private TransactionManager $transactions, private PurchaseCreditInvoiceRepository $credits, private PurchaseCreditSourceReader $sources, private PurchaseCreditIdentityGenerator $ids, private PurchaseCreditLineFactory $lines, private PurchaseCreditClock $clock) {}

    public function execute(AdministrationId $admin, PurchaseCreditDraftInput $input, UserId $actor): CreatePurchaseCreditInvoiceResult
    {
        return $this->transactions->run(function () use ($admin, $input, $actor) {
            $source = $this->sources->read($admin, $input->sourceInvoiceId, true);
            if ($source === null || $source->invoice->status()->value !== 'posted' || $source->invoice->currency()->code() !== 'EUR') {
                return new CreatePurchaseCreditInvoiceResult(PurchaseCreditMutationResult::InvalidSource);
            }$lines = $this->lines->selected($source, $input->selectedLineIds);
            if ($lines === null) {
                return new CreatePurchaseCreditInvoiceResult(PurchaseCreditMutationResult::InvalidLines);
            }$id = $this->ids->creditId();
            $credit = new PurchaseCreditInvoice($id, $input->number, $admin, $source->invoice->supplierId(), $source->invoice->currency(), $input->creditDate, $source->invoice->id(), PurchaseCreditInvoiceStatus::Draft, $source->invoice->supplierSnapshot(), $source->invoice->documentAddress(), $input->receivedDate, max($input->creditDate, $input->receivedDate), $source->invoice->supplyDate(), $source->payableOpenItemId, $actor, $this->clock->now(), $lines);

            return $this->credits->create($credit) ? new CreatePurchaseCreditInvoiceResult(PurchaseCreditMutationResult::Success, $id) : new CreatePurchaseCreditInvoiceResult(PurchaseCreditMutationResult::DuplicateSupplierCreditInvoice);
        });
    }
}
