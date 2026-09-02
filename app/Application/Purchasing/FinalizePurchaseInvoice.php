<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Application\Administration\AdministrationFiscalPartyReader;
use App\Application\Fiscal\TaxTreatmentDefinitionRepository;
use App\Application\Fiscal\TaxTreatmentDefinitionSelectionStatus;
use App\Application\Relations\RelationFiscalPartyReader;
use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Entities\TaxTreatmentDefinition;
use App\Domain\Fiscal\Enums\DeductibilityPolicy;
use App\Domain\Fiscal\Enums\TaxTreatmentType;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Purchasing\Enums\PurchaseInvoiceStatus;
use App\Domain\Purchasing\Enums\PurchaseSupplyClassification;
use App\Domain\Purchasing\ValueObjects\InternationalPurchaseSourceFacts;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;
use App\Domain\Purchasing\ValueObjects\PurchaseTaxTreatmentSnapshot;

final readonly class FinalizePurchaseInvoice
{
    public function __construct(private TransactionManager $transactions, private PurchaseInvoiceRepository $repository, private PurchaseInvoiceClock $clock, private TaxTreatmentDefinitionRepository $treatments, private AdministrationFiscalPartyReader $administrationFiscalParties, private RelationFiscalPartyReader $relationFiscalParties) {}

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
                $frozenAt = $this->clock->now();
                foreach ($invoice->lines() as $line) {
                    if ($line->internationalSourceFacts() === null) {
                        continue;
                    }
                    $selection = $this->treatments->resolveActiveForTaxCode($admin, $line->tax()->id);
                    if ($selection->status === TaxTreatmentDefinitionSelectionStatus::Missing) {
                        return FinalizePurchaseInvoiceResult::MissingTaxTreatment;
                    }
                    if ($selection->status === TaxTreatmentDefinitionSelectionStatus::IntegrityFailure || $selection->definition === null) {
                        return FinalizePurchaseInvoiceResult::TaxTreatmentIntegrityFailure;
                    }
                    $definition = $selection->definition;
                    $supplier = $this->relationFiscalParties->findFiscalParty($admin, $invoice->supplierSnapshot()->relationId);
                    $customer = $this->administrationFiscalParties->findFiscalParty($admin);
                    $inputFacts = $line->internationalSourceFacts();
                    if ($supplier === null || $customer === null) {
                        return FinalizePurchaseInvoiceResult::IncompleteFiscalPartyFacts;
                    }
                    if ($supplier->fiscalJurisdiction === null || $customer->fiscalJurisdiction === null) {
                        return FinalizePurchaseInvoiceResult::IncompleteFiscalPartyFacts;
                    }
                    $facts = new InternationalPurchaseSourceFacts(
                        $supplier->fiscalJurisdiction->value(),
                        $customer->fiscalJurisdiction->value(),
                        $supplier->vatIdentificationNumber?->toString(),
                        $customer->vatIdentificationNumber?->toString(),
                        $inputFacts->classification,
                        $inputFacts->businessToBusiness,
                        $inputFacts->arrivesInNetherlands,
                        $inputFacts->generalRuleConfirmed,
                        $inputFacts->specialPlaceOfSupply,
                        $inputFacts->foreignSupplierVat,
                        $inputFacts->importOrCustoms,
                        $inputFacts->evidence,
                        $inputFacts->deductibilityRationale,
                        $inputFacts->deductibilityPolicyVersion,
                    );
                    $failure = $this->validateInternational($definition, $facts, $line->deductibility()?->value());
                    if ($failure !== null) {
                        return $failure;
                    }
                    $invoice->replaceLine($line->withTreatmentSnapshot(new PurchaseTaxTreatmentSnapshot(
                        $definition->id(), $definition->version(), $definition->treatmentType(), $definition->jurisdiction(),
                        $definition->vatRate(), $definition->supplierVatMode(), $definition->deductibilityPolicy(),
                        $definition->legDefinitions(), $line->deductibility(), $facts, $actor, $frozenAt,
                    )));
                }
                $invoice->finalize($actor, $frozenAt);
            } catch (\DomainException|\InvalidArgumentException) {
                return FinalizePurchaseInvoiceResult::ValidationFailed;
            }
            $this->repository->save($invoice);

            return FinalizePurchaseInvoiceResult::Success;
        });
    }

    private function validateInternational(TaxTreatmentDefinition $definition, InternationalPurchaseSourceFacts $facts, ?int $deductibility): ?FinalizePurchaseInvoiceResult
    {
        if (! in_array($definition->treatmentType(), [TaxTreatmentType::EuGoodsAcquisitionNl, TaxTreatmentType::EuB2bGeneralRuleService, TaxTreatmentType::NonEuB2bGeneralRuleService], true)) {
            return FinalizePurchaseInvoiceResult::UnsupportedTaxTreatment;
        }
        if ($facts->importOrCustoms) {
            return FinalizePurchaseInvoiceResult::UnsupportedImportCustoms;
        }
        if ($facts->foreignSupplierVat) {
            return FinalizePurchaseInvoiceResult::UnsupportedForeignVat;
        }
        if ($facts->specialPlaceOfSupply) {
            return FinalizePurchaseInvoiceResult::UnsupportedTaxTreatment;
        }
        if ($deductibility === null || match ($definition->deductibilityPolicy()) {
            DeductibilityPolicy::NotApplicable, DeductibilityPolicy::FixedZero => $deductibility !== 0,
            DeductibilityPolicy::FixedFull => $deductibility !== 10000,
            DeductibilityPolicy::UserSpecifiedLineRate => trim((string) $facts->deductibilityRationale) === '',
        }) {
            return FinalizePurchaseInvoiceResult::InvalidDeductibility;
        }
        $partyFactsComplete = $facts->businessToBusiness && $facts->customerJurisdiction === 'NL'
            && $facts->supplierJurisdiction !== 'NL' && $facts->supplierVatIdentity !== null && $facts->customerVatIdentity !== null;
        $scenarioComplete = match ($definition->treatmentType()) {
            TaxTreatmentType::EuGoodsAcquisitionNl => $facts->classification === PurchaseSupplyClassification::Goods && $facts->arrivesInNetherlands && trim((string) $facts->evidence) !== '',
            TaxTreatmentType::EuB2bGeneralRuleService, TaxTreatmentType::NonEuB2bGeneralRuleService => $facts->classification === PurchaseSupplyClassification::GeneralRuleService && $facts->generalRuleConfirmed,
            default => false,
        };

        return $partyFactsComplete && $scenarioComplete ? null : FinalizePurchaseInvoiceResult::IncompleteFiscalPartyFacts;
    }
}
