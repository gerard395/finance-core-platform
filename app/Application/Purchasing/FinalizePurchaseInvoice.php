<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Purchasing\Enums\PurchaseInvoiceStatus;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;

final readonly class FinalizePurchaseInvoice
{
    public function __construct(private TransactionManager $transactions, private PurchaseInvoiceRepository $repository, private PurchaseInvoiceClock $clock) {}

    public function execute(AdministrationId $admin, PurchaseInvoiceId $id, UserId $actor): FinalizePurchaseInvoiceResult
    {
        return $this->transactions->run(function () use ($admin, $id, $actor): FinalizePurchaseInvoiceResult {
            $invoice = $this->repository->findForUpdate($admin, $id);
            if ($invoice === null) {
                return FinalizePurchaseInvoiceResult::NotFound;
            }
            if ($invoice->status() === PurchaseInvoiceStatus::Finalized) {
                return FinalizePurchaseInvoiceResult::AlreadyFinalized;
            }
            if ($invoice->status() !== PurchaseInvoiceStatus::Draft) {
                return FinalizePurchaseInvoiceResult::InvalidState;
            }
            try {
                $invoice->finalize($actor, $this->clock->now());
            } catch (\DomainException|\InvalidArgumentException) {
                return FinalizePurchaseInvoiceResult::ValidationFailed;
            }
            $this->repository->save($invoice);

            return FinalizePurchaseInvoiceResult::Success;
        });
    }
}
