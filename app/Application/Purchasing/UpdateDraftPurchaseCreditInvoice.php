<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Purchasing\Enums\PurchaseCreditInvoiceStatus;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceId;

final readonly class UpdateDraftPurchaseCreditInvoice
{
    public function __construct(private TransactionManager $transactions, private PurchaseCreditInvoiceRepository $credits, private PurchaseCreditSourceReader $sources, private PurchaseCreditLineFactory $lines) {}

    public function execute(AdministrationId $admin, PurchaseCreditInvoiceId $id, PurchaseCreditDraftInput $input): PurchaseCreditMutationResult
    {
        return $this->transactions->run(function () use ($admin, $id, $input) {
            $credit = $this->credits->findForUpdate($admin, $id);
            if ($credit === null) {
                return PurchaseCreditMutationResult::NotFound;
            }if ($credit->status() !== PurchaseCreditInvoiceStatus::Draft || ! $credit->sourcePurchaseInvoiceId()?->equals($input->sourceInvoiceId)) {
                return PurchaseCreditMutationResult::InvalidState;
            }$source = $this->sources->read($admin, $input->sourceInvoiceId, true);
            if ($source === null || $source->invoice->status()->value !== 'posted') {
                return PurchaseCreditMutationResult::InvalidSource;
            }$lines = $this->lines->selected($source, $input->selectedLineIds);
            if ($lines === null) {
                return PurchaseCreditMutationResult::InvalidLines;
            }$credit->replaceDraft($input->number, $input->creditDate, $input->receivedDate, $lines);

            return $this->credits->save($credit) ? PurchaseCreditMutationResult::Success : PurchaseCreditMutationResult::DuplicateSupplierCreditInvoice;
        });
    }
}
