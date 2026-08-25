<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Purchasing\Enums\PurchaseInvoiceStatus;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;

final readonly class CancelPurchaseInvoice
{
    public function __construct(private TransactionManager $transactions, private PurchaseInvoiceRepository $repository) {}

    public function execute(AdministrationId $admin, PurchaseInvoiceId $id): CancelPurchaseInvoiceResult
    {
        return $this->transactions->run(function () use ($admin, $id): CancelPurchaseInvoiceResult {
            $invoice = $this->repository->findForUpdate($admin, $id);
            if ($invoice === null) {
                return CancelPurchaseInvoiceResult::NotFound;
            }
            if ($invoice->status() === PurchaseInvoiceStatus::Cancelled) {
                return CancelPurchaseInvoiceResult::AlreadyCancelled;
            }
            try {
                $invoice->cancel();
            } catch (\DomainException) {
                return CancelPurchaseInvoiceResult::InvalidState;
            }
            $this->repository->save($invoice);

            return CancelPurchaseInvoiceResult::Success;
        });
    }
}
