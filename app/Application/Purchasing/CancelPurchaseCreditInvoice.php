<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Purchasing\Enums\PurchaseCreditInvoiceStatus;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceId;

final readonly class CancelPurchaseCreditInvoice
{
    public function __construct(private TransactionManager $transactions, private PurchaseCreditInvoiceRepository $credits, private PurchaseCreditClock $clock) {}

    public function execute(AdministrationId $admin, PurchaseCreditInvoiceId $id, UserId $actor): PurchaseCreditMutationResult
    {
        return $this->transactions->run(function () use ($admin, $id, $actor) {
            $credit = $this->credits->findForUpdate($admin, $id);
            if ($credit === null) {
                return PurchaseCreditMutationResult::NotFound;
            }if ($credit->status() === PurchaseCreditInvoiceStatus::Cancelled) {
                return PurchaseCreditMutationResult::AlreadyCancelled;
            }try {
                $credit->cancel($actor, $this->clock->now());
            } catch (\DomainException) {
                return PurchaseCreditMutationResult::InvalidState;
            }$this->credits->save($credit);

            return PurchaseCreditMutationResult::Success;
        });
    }
}
