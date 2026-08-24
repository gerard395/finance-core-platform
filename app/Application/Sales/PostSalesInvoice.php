<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Application\Accounting\JournalEntryStore;
use App\Application\Accounting\OpenItemStore;
use App\Application\Fiscal\TaxPostingStore;
use App\Application\Shared\TransactionManager;
use App\Domain\Accounting\Entities\OpenItem;
use App\Domain\Accounting\Enums\OpenItemType;
use App\Domain\Accounting\ValueObjects\JournalEntryReference;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Enums\SalesInvoiceStatus;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use RuntimeException;
use Throwable;

final readonly class PostSalesInvoice
{
    public function __construct(
        private TransactionManager $transactions,
        private SalesInvoicePostingSource $invoices,
        private SalesInvoiceUpdater $invoiceUpdater,
        private SalesPostingConfigurationReader $configurationReader,
        private SalesInvoicePostingRepository $postingRepository,
        private PostSalesInvoiceWithTax $fiscalPosting,
        private JournalEntryStore $journalEntries,
        private TaxPostingStore $taxPostings,
        private OpenItemStore $openItems,
        private SalesInvoicePostingIdentityGenerator $identities,
        private SalesInvoicePostingClock $clock,
        private SalesInvoiceReadinessChecker $readiness,
    ) {}

    public function execute(AdministrationId $administrationId, SalesInvoiceId $invoiceId): PostSalesInvoiceResult
    {
        try {
            return $this->transactions->run(function () use ($administrationId, $invoiceId): PostSalesInvoiceResult {
                $invoice = $this->invoices->findLockedForAdministration($administrationId, $invoiceId);

                if ($invoice === null) {
                    return PostSalesInvoiceResult::forStatus(PostSalesInvoiceStatus::NotFound);
                }

                $existingPosting = $this->postingRepository->findForInvoice($administrationId, $invoiceId);

                if ($existingPosting !== null) {
                    return PostSalesInvoiceResult::forStatus(
                        in_array($invoice->status(), [SalesInvoiceStatus::Posted, SalesInvoiceStatus::Paid], true)
                            ? PostSalesInvoiceStatus::AlreadyPosted
                            : PostSalesInvoiceStatus::FinancialStateInconsistent,
                    );
                }

                if (in_array($invoice->status(), [SalesInvoiceStatus::Posted, SalesInvoiceStatus::Paid], true)) {
                    return PostSalesInvoiceResult::forStatus(PostSalesInvoiceStatus::FinancialStateInconsistent);
                }

                if ($invoice->status() !== SalesInvoiceStatus::Finalized) {
                    return PostSalesInvoiceResult::forStatus(PostSalesInvoiceStatus::InvalidState);
                }

                if ($this->readiness->check($invoice)->status() !== SalesInvoiceReadinessStatus::Ready) {
                    return PostSalesInvoiceResult::forStatus(PostSalesInvoiceStatus::FinancialStateInconsistent);
                }

                $configurationResult = $this->configurationReader->read($administrationId);
                if ($configurationResult->status() === SalesPostingConfigurationReadStatus::Missing) {
                    return PostSalesInvoiceResult::forStatus(PostSalesInvoiceStatus::ConfigurationMissing);
                }
                if ($configurationResult->status() === SalesPostingConfigurationReadStatus::InvalidReference) {
                    return PostSalesInvoiceResult::forStatus(PostSalesInvoiceStatus::ConfigurationInvalid);
                }

                $configuration = $configurationResult->configuration();
                $customer = $invoice->customerSnapshot();
                if ($configuration === null || $customer === null) {
                    return PostSalesInvoiceResult::forStatus(PostSalesInvoiceStatus::FinancialStateInconsistent);
                }

                $fiscalLines = [];
                foreach ($invoice->lines() as $line) {
                    $tax = $line->taxSnapshot();
                    if ($tax === null) {
                        return PostSalesInvoiceResult::forStatus(PostSalesInvoiceStatus::FinancialStateInconsistent);
                    }
                    $fiscalLines[] = new SalesFiscalLineInput(
                        $line->id(),
                        $tax->forCalculation(),
                        $configuration->revenueLedgerAccountId(),
                        $configuration->outputVatLedgerAccountId(),
                        $this->identities->journalEntryLineId(),
                        $tax->taxRate()->value() === '0' ? null : $this->identities->journalEntryLineId(),
                        $this->identities->taxPostingId(),
                    );
                }

                $postingDate = new PostingDate($invoice->invoiceDate());
                $debtorLineId = $this->identities->journalEntryLineId();
                $fiscalResult = $this->fiscalPosting->execute(
                    $invoice,
                    $fiscalLines,
                    [],
                    $configuration->salesJournalId(),
                    $configuration->accountsReceivableLedgerAccountId(),
                    $debtorLineId,
                    $postingDate,
                    new JournalEntryReference($invoice->number()->value()),
                );
                $journalEntry = $fiscalResult->postingResult()->journalEntry();
                if ($journalEntry === null) {
                    return PostSalesInvoiceResult::forStatus(PostSalesInvoiceStatus::PostingFailure);
                }

                $debtorLine = $journalEntry->line($debtorLineId);
                $gross = $debtorLine?->debit();
                if ($gross === null) {
                    throw new SalesInvoicePostingTransactionFailed;
                }

                $openItem = new OpenItem(
                    $this->identities->openItemId(),
                    $administrationId,
                    $customer->relationId(),
                    $journalEntry->id(),
                    OpenItemType::Receivable,
                    $gross,
                    $postingDate,
                );

                $this->journalEntries->append($journalEntry);
                foreach ($fiscalResult->taxPostings() as $taxPosting) {
                    $this->taxPostings->append($taxPosting);
                }
                $this->openItems->append($openItem);

                $linkageResult = $this->postingRepository->append(new SalesInvoicePosting(
                    $administrationId,
                    $invoiceId,
                    $journalEntry->id(),
                    $openItem->id(),
                    $this->clock->now(),
                ));
                if ($linkageResult !== SalesInvoicePostingAppendResult::Appended) {
                    throw new SalesInvoicePostingTransactionFailed;
                }

                $invoice->post();
                if ($this->invoiceUpdater->update($administrationId, $invoice) !== SalesInvoiceWriteResult::Success) {
                    throw new SalesInvoicePostingTransactionFailed;
                }

                return PostSalesInvoiceResult::success(
                    $journalEntry->id(),
                    $openItem->id(),
                    array_map(static fn ($taxPosting) => $taxPosting->id(), $fiscalResult->taxPostings()),
                );
            });
        } catch (Throwable) {
            return PostSalesInvoiceResult::forStatus(PostSalesInvoiceStatus::PostingFailure);
        }
    }
}

final class SalesInvoicePostingTransactionFailed extends RuntimeException {}
