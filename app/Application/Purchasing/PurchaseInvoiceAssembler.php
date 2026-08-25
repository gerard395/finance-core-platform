<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Accounting\Enums\LedgerAccountType;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Services\TaxCalculation;
use App\Domain\Purchasing\Entities\PurchaseInvoiceLine;
use App\Domain\Purchasing\ValueObjects\PurchaseAccountSnapshot;
use App\Domain\Purchasing\ValueObjects\PurchaseTaxSnapshot;

final readonly class PurchaseInvoiceAssembler
{
    public function __construct(private PurchaseInvoiceMasterDataReader $masterData, private PurchaseInvoiceIdentityGenerator $ids, private TaxCalculation $taxCalculation) {}

    public function supplier(AdministrationId $admin, PurchaseInvoiceDraftInput $input): mixed
    {
        return $this->masterData->activeSupplier($admin, $input->supplierId);
    }

    public function supplierExists(AdministrationId $admin, PurchaseInvoiceDraftInput $input): bool
    {
        return $this->masterData->supplierExists($admin, $input->supplierId);
    }

    /** @return list<PurchaseInvoiceLine>|null */
    public function lines(AdministrationId $admin, PurchaseInvoiceDraftInput $input): ?array
    {
        $lines = [];
        foreach ($input->lines as $source) {
            $account = $this->masterData->activeLineAccount($admin, $source->ledgerAccountId);
            $tax = $this->masterData->activeInputTaxCode($admin, $source->taxCodeId);
            if ($account === null || ! in_array($account->type(), [LedgerAccountType::Expense, LedgerAccountType::Asset], true) || $tax === null) {
                return null;
            }
            if (! $source->fullyDeductible && $tax->rate()->value() !== '0') {
                return null;
            }
            try {
                $calculation = $this->taxCalculation->calculate($source->unitPrice->multiply($source->quantity->value()), $tax->toTaxCode());
                $taxSnapshot = new PurchaseTaxSnapshot($tax->id(), $tax->code(), $tax->name(), $tax->rate(), $tax->direction(), $tax->treatment(), $tax->vatReturnClassification(), $tax->icpClassification());
            } catch (\InvalidArgumentException|\DomainException) {
                return null;
            }
            $lines[] = new PurchaseInvoiceLine($this->ids->lineId(), $source->description, $source->quantity, $source->unitPrice, new PurchaseAccountSnapshot($account->id(), $account->code(), $account->name(), $account->type()), $taxSnapshot, $calculation->netAmount(), $calculation->taxAmount(), $calculation->grossAmount());
        }

        return $lines;
    }
}
