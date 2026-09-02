<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Application\Accounting\AccountingPeriodPostingDecisionStatus;
use App\Application\Accounting\AccountingPeriodPostingGuard;
use App\Application\Accounting\JournalEntryStore;
use App\Application\Accounting\OpenItemStore;
use App\Application\Fiscal\TaxPostingStore;
use App\Application\Shared\TransactionManager;
use App\Domain\Accounting\Entities\JournalEntryLine;
use App\Domain\Accounting\Entities\OpenItem;
use App\Domain\Accounting\Enums\LedgerAccountType;
use App\Domain\Accounting\Enums\OpenItemSide;
use App\Domain\Accounting\Enums\OpenItemType;
use App\Domain\Accounting\Requests\PostingRequest;
use App\Domain\Accounting\Services\PostingEngine;
use App\Domain\Accounting\ValueObjects\JournalEntryReference;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Entities\TaxPosting;
use App\Domain\Fiscal\Entities\TaxTreatmentDefinition;
use App\Domain\Fiscal\Enums\TaxLegRole;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\Enums\TaxPostingType;
use App\Domain\Fiscal\Enums\TaxSourceDocumentType;
use App\Domain\Fiscal\Enums\TaxTreatment;
use App\Domain\Fiscal\Services\PurchaseTaxTreatmentCalculation;
use App\Domain\Fiscal\ValueObjects\TaxPostingLegSnapshot;
use App\Domain\Fiscal\ValueObjects\TaxSourceDocumentId;
use App\Domain\Fiscal\ValueObjects\TaxSourceLineId;
use App\Domain\Purchasing\Enums\PurchaseInvoiceStatus;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;
use App\Domain\Shared\Finance\Money;
use Throwable;

final readonly class PostPurchaseInvoice
{
    public function __construct(
        private TransactionManager $transactions,
        private PurchaseInvoiceRepository $invoices,
        private PurchaseInvoicePostingRepository $postings,
        private PurchasePostingConfigurationReader $configurations,
        private PurchaseInvoiceMasterDataReader $masterData,
        private PostingEngine $postingEngine,
        private JournalEntryStore $journalEntries,
        private TaxPostingStore $taxPostings,
        private OpenItemStore $openItems,
        private PurchaseInvoicePostingIdentityGenerator $identities,
        private PurchaseInvoicePostingClock $clock,
        private AccountingPeriodPostingGuard $periodGuard,
        private PurchaseTaxTreatmentCalculation $internationalTaxCalculation,
    ) {}

    public function execute(AdministrationId $administrationId, PurchaseInvoiceId $invoiceId, PostingDate $postingDate): PostPurchaseInvoiceResult
    {
        try {
            return $this->transactions->run(function () use ($administrationId, $invoiceId, $postingDate): PostPurchaseInvoiceResult {
                $invoice = $this->invoices->findForUpdate($administrationId, $invoiceId);
                if ($invoice === null) {
                    return PostPurchaseInvoiceResult::status(PostPurchaseInvoiceStatus::NotFound);
                }
                $existing = $this->postings->findForInvoice($administrationId, $invoiceId);
                if ($existing !== null) {
                    return PostPurchaseInvoiceResult::status($invoice->status() === PurchaseInvoiceStatus::Posted ? PostPurchaseInvoiceStatus::AlreadyPosted : PostPurchaseInvoiceStatus::PostingFailure);
                }
                if ($invoice->status() === PurchaseInvoiceStatus::Posted) {
                    return PostPurchaseInvoiceResult::status(PostPurchaseInvoiceStatus::PostingFailure);
                }
                if ($invoice->status() !== PurchaseInvoiceStatus::Finalized) {
                    return PostPurchaseInvoiceResult::status(PostPurchaseInvoiceStatus::InvalidState);
                }

                $period = $this->periodGuard->lockForPosting($administrationId, $postingDate);
                if ($period->status !== AccountingPeriodPostingDecisionStatus::Open) {
                    return PostPurchaseInvoiceResult::status(match ($period->status) {
                        AccountingPeriodPostingDecisionStatus::Closed => PostPurchaseInvoiceStatus::PeriodClosed,
                        AccountingPeriodPostingDecisionStatus::NoPeriod => PostPurchaseInvoiceStatus::NoAccountingPeriod,
                        AccountingPeriodPostingDecisionStatus::IntegrityFailure => PostPurchaseInvoiceStatus::PeriodIntegrityFailure,
                        AccountingPeriodPostingDecisionStatus::Open => throw new \LogicException,
                    });
                }

                $requiresInputVat = false;
                foreach ($invoice->lines() as $line) {
                    $requiresInputVat = $requiresInputVat || ($line->treatmentSnapshot()?->deductibility->isZero() === false)
                        || ($line->treatmentSnapshot() === null && $line->taxAmount()->isPositive());
                }
                $configurationResult = $this->configurations->readForPosting($administrationId, $requiresInputVat);
                if ($configurationResult->status === PurchasePostingConfigurationReadStatus::Missing) {
                    return PostPurchaseInvoiceResult::status(PostPurchaseInvoiceStatus::ConfigurationMissing);
                }
                if ($configurationResult->status === PurchasePostingConfigurationReadStatus::InvalidReference) {
                    if ($configurationResult->invalidReferences === [PurchasePostingConfigurationInvalidReference::InputVat]) {
                        return PostPurchaseInvoiceResult::status(PostPurchaseInvoiceStatus::MissingTaxConfiguration);
                    }

                    return PostPurchaseInvoiceResult::status(PostPurchaseInvoiceStatus::ConfigurationInvalid);
                }
                $configuration = $configurationResult->configuration;
                if ($configuration === null || $invoice->currency()->code() !== 'EUR') {
                    return PostPurchaseInvoiceResult::status(PostPurchaseInvoiceStatus::FiscalStateInvalid);
                }

                $description = 'Purchase invoice '.$invoice->supplierInvoiceNumber()->value();
                $journalLines = [];
                $taxFacts = [];
                $payableTotal = Money::zero($invoice->currency());
                foreach ($invoice->lines() as $line) {
                    $account = $this->masterData->activeLineAccount($administrationId, $line->account()->id);
                    if ($account === null || $account->type() !== $line->account()->type || ! in_array($account->type(), [LedgerAccountType::Expense, LedgerAccountType::Asset], true)) {
                        return PostPurchaseInvoiceResult::status(PostPurchaseInvoiceStatus::ConfigurationInvalid);
                    }
                    $tax = $line->tax();
                    $snapshot = $line->treatmentSnapshot();
                    if ($line->internationalSourceFacts() !== null && $snapshot === null) {
                        return PostPurchaseInvoiceResult::status(PostPurchaseInvoiceStatus::MissingTaxTreatment);
                    }
                    if ($snapshot !== null) {
                        if ($configuration->vatPayableLedgerAccountId === null) {
                            return PostPurchaseInvoiceResult::status(PostPurchaseInvoiceStatus::MissingTaxConfiguration);
                        }
                        $definition = new TaxTreatmentDefinition($snapshot->definitionId, $administrationId, $tax->id, $snapshot->definitionVersion, $snapshot->treatmentType, $snapshot->jurisdiction, $snapshot->vatRate, $snapshot->supplierVatMode, $snapshot->deductibilityPolicy, $snapshot->legDefinitions);
                        $groupId = $this->identities->taxTreatmentGroupId();
                        $legIds = [];
                        foreach ($snapshot->legDefinitions as $legDefinition) {
                            $legIds[$legDefinition->role->value] = $this->identities->taxLegId();
                        }
                        try {
                            $calculation = $this->internationalTaxCalculation->calculate($definition, $groupId, $legIds, $line->net(), Money::zero($invoice->currency()), $snapshot->deductibility);
                        } catch (\DomainException|\InvalidArgumentException) {
                            return PostPurchaseInvoiceResult::status(PostPurchaseInvoiceStatus::InvalidDeductibility);
                        }
                        if (! $calculation->amounts->deductibleVat->isZero()) {
                            $inputVat = $this->masterData->activeLedgerAccount($administrationId, $configuration->inputVatLedgerAccountId);
                            if ($inputVat === null || $inputVat->type() !== LedgerAccountType::Asset) {
                                return PostPurchaseInvoiceResult::status(PostPurchaseInvoiceStatus::MissingTaxConfiguration);
                            }
                        }
                        $baseLineId = $this->identities->journalEntryLineId();
                        $baseDebit = $calculation->amounts->supplierNet->add($calculation->amounts->nonDeductibleTaxCost);
                        $journalLines[] = new JournalEntryLine($baseLineId, $line->account()->id, $baseDebit, null, $description.' line '.$line->id()->toString());
                        $realized = [];
                        foreach ($calculation->legs as $leg) {
                            $taxLineId = $this->identities->journalEntryLineId();
                            $accountId = $leg->role === TaxLegRole::VatPayable ? $configuration->vatPayableLedgerAccountId : $configuration->inputVatLedgerAccountId;
                            $journalLines[] = $leg->role === TaxLegRole::VatPayable
                                ? new JournalEntryLine($taxLineId, $accountId, null, $leg->amount, $description.' VAT payable')
                                : new JournalEntryLine($taxLineId, $accountId, $leg->amount, null, $description.' deductible VAT');
                            $realized[] = [$leg, $taxLineId];
                        }
                        $payableTotal = $payableTotal->add($calculation->amounts->supplierGross);
                        $taxFacts[] = ['international', $line, $baseLineId, $calculation, $realized];

                        continue;
                    }
                    if ($tax->direction !== TaxPostingDirection::Input || ! in_array($tax->treatment, [TaxTreatment::DomesticStandard, TaxTreatment::DomesticReduced, TaxTreatment::ZeroRated, TaxTreatment::Exempt, TaxTreatment::OutsideScope], true)) {
                        return PostPurchaseInvoiceResult::status(PostPurchaseInvoiceStatus::FiscalStateInvalid);
                    }
                    $baseLineId = $this->identities->journalEntryLineId();
                    $taxLineId = $line->taxAmount()->isPositive() ? $this->identities->journalEntryLineId() : null;
                    if ($taxLineId !== null) {
                        $inputVat = $this->masterData->activeLedgerAccount($administrationId, $configuration->inputVatLedgerAccountId);
                        if ($inputVat === null || $inputVat->type() !== LedgerAccountType::Asset) {
                            return PostPurchaseInvoiceResult::status(PostPurchaseInvoiceStatus::MissingTaxConfiguration);
                        }
                    }
                    $journalLines[] = new JournalEntryLine($baseLineId, $line->account()->id, $line->net(), null, $description.' line '.$line->id()->toString());
                    if ($taxLineId !== null) {
                        $journalLines[] = new JournalEntryLine($taxLineId, $configuration->inputVatLedgerAccountId, $line->taxAmount(), null, $description.' input VAT');
                    }
                    $taxFacts[] = [$line, $baseLineId, $taxLineId, $this->identities->taxPostingId()];
                    $payableTotal = $payableTotal->add($line->gross());
                }
                $payableLineId = $this->identities->journalEntryLineId();
                $journalLines[] = new JournalEntryLine($payableLineId, $configuration->accountsPayableLedgerAccountId, null, $payableTotal, $description.' payable');
                $posting = $this->postingEngine->post(new PostingRequest($administrationId, $configuration->purchaseJournalId, $postingDate, new JournalEntryReference($invoice->supplierInvoiceNumber()->value()), $journalLines));
                $entry = $posting->journalEntry();
                if (! $posting->isSuccess() || $entry === null) {
                    return PostPurchaseInvoiceResult::status(PostPurchaseInvoiceStatus::PostingFailure);
                }

                $this->journalEntries->append($entry);
                foreach ($taxFacts as $fact) {
                    if ($fact[0] === 'international') {
                        [, $line, $baseLineId, $calculation, $realized] = $fact;
                        foreach ($realized as [$leg, $taxLineId]) {
                            $s = $calculation->snapshot;
                            $this->taxPostings->append(new TaxPosting($leg->id->taxPostingId(), $administrationId, $line->tax()->id, $leg->rate, $leg->taxableBasis, $leg->amount, $leg->direction, TaxSourceDocumentType::PurchaseInvoice, new TaxSourceDocumentId($invoice->id()->uuid()), new TaxSourceLineId($line->id()->uuid()), new PostingDate($invoice->fiscalReportingDate()), $entry->id(), $baseLineId, $taxLineId, TaxPostingType::Original, null, $line->tax()->treatment, $line->tax()->vatReturn, $line->tax()->icp, new TaxPostingLegSnapshot($s->definitionId, $s->definitionVersion, $leg->groupId, $leg->role, $s->treatmentType, $s->jurisdiction, $leg->reportingClassification, $s->deductibility, $s->amounts->assessedVat(), $s->amounts->deductibleVat, $s->amounts->nonDeductibleTaxCost, $s->supplierVatMode)));
                        }

                        continue;
                    }
                    [$line, $baseLineId, $taxLineId, $taxPostingId] = $fact;
                    $tax = $line->tax();
                    $this->taxPostings->append(new TaxPosting($taxPostingId, $administrationId, $tax->id, $tax->rate, $line->net(), $line->taxAmount(), TaxPostingDirection::Input, TaxSourceDocumentType::PurchaseInvoice, new TaxSourceDocumentId($invoice->id()->uuid()), new TaxSourceLineId($line->id()->uuid()), new PostingDate($invoice->fiscalReportingDate()), $entry->id(), $baseLineId, $taxLineId, TaxPostingType::Original, null, $tax->treatment, $tax->vatReturn, $tax->icp));
                }
                $openItem = new OpenItem($this->identities->openItemId(), $administrationId, $invoice->supplierSnapshot()->relationId, $entry->id(), $configuration->accountsPayableLedgerAccountId, OpenItemType::Payable, $payableTotal, $postingDate, OpenItemSide::Credit, $invoice->dueDate());
                $this->openItems->append($openItem);
                if (! $this->postings->append(new PurchaseInvoicePosting($administrationId, $invoiceId, $entry->id(), $openItem->id(), $postingDate, $this->clock->now()))) {
                    throw new \RuntimeException('Duplicate posting linkage.');
                }
                $invoice->markPosted();
                if (! $this->invoices->save($invoice)) {
                    throw new \RuntimeException('Purchase invoice status persistence failed.');
                }

                return PostPurchaseInvoiceResult::success($entry->id(), $openItem->id());
            });
        } catch (Throwable) {
            return PostPurchaseInvoiceResult::status(PostPurchaseInvoiceStatus::PostingFailure);
        }
    }
}
