<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Application\Accounting\AccountingPeriodPostingDecisionStatus;
use App\Application\Accounting\AccountingPeriodPostingGuard;
use App\Application\Accounting\JournalEntryStore;
use App\Application\Accounting\MatchOpenItems;
use App\Application\Accounting\MatchOpenItemsStatus;
use App\Application\Accounting\OpenItemMatchRepository;
use App\Application\Accounting\OpenItemStore;
use App\Application\Fiscal\TaxPostingStore;
use App\Application\Shared\TransactionManager;
use App\Domain\Accounting\Entities\OpenItem;
use App\Domain\Accounting\Enums\OpenItemSide;
use App\Domain\Accounting\Enums\OpenItemType;
use App\Domain\Accounting\ValueObjects\JournalEntryReference;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Enums\SalesCreditInvoiceStatus;
use App\Domain\Sales\Enums\SalesInvoiceStatus;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;
use RuntimeException;
use Throwable;

final readonly class PostSalesCreditInvoice
{
    public function __construct(
        private TransactionManager $transactions,
        private SalesCreditInvoicePostingSource $credits,
        private SalesCreditInvoiceUpdater $creditUpdater,
        private SalesCreditSourceReader $sourceReader,
        private SalesInvoicePostingRepository $sourcePostings,
        private OpenItemMatchRepository $openItemMatches,
        private SalesPostingConfigurationReader $configurationReader,
        private PostSalesCreditInvoiceWithTax $fiscalPosting,
        private JournalEntryStore $journalEntries,
        private TaxPostingStore $taxPostings,
        private OpenItemStore $openItems,
        private MatchOpenItems $matching,
        private SalesCreditInvoicePostingRepository $postingRepository,
        private SalesCreditInvoicePostingIdentityGenerator $identities,
        private SalesCreditInvoicePostingClock $clock,
        private AccountingPeriodPostingGuard $periodGuard,
    ) {}

    public function execute(AdministrationId $administrationId, SalesCreditInvoiceId $creditInvoiceId): PostSalesCreditInvoiceResult
    {
        try {
            return $this->transactions->run(function () use ($administrationId, $creditInvoiceId): PostSalesCreditInvoiceResult {
                $credit = $this->credits->findLockedForAdministration($administrationId, $creditInvoiceId);
                if ($credit === null) {
                    return PostSalesCreditInvoiceResult::forStatus(PostSalesCreditInvoiceStatus::NotFound);
                }
                $existing = $this->postingRepository->findForCreditInvoice($administrationId, $creditInvoiceId);
                if ($existing !== null) {
                    return PostSalesCreditInvoiceResult::forStatus($credit->status() === SalesCreditInvoiceStatus::Posted
                        ? PostSalesCreditInvoiceStatus::AlreadyPosted
                        : PostSalesCreditInvoiceStatus::FinancialStateInconsistent);
                }
                if ($credit->status() === SalesCreditInvoiceStatus::Posted) {
                    return PostSalesCreditInvoiceResult::forStatus(PostSalesCreditInvoiceStatus::FinancialStateInconsistent);
                }
                if ($credit->status() !== SalesCreditInvoiceStatus::Finalized) {
                    return PostSalesCreditInvoiceResult::forStatus(PostSalesCreditInvoiceStatus::InvalidState);
                }

                $sourceResult = $this->sourceReader->read($administrationId, $credit->sourceInvoiceId(), $creditInvoiceId);
                $source = $sourceResult->invoice();
                if ($sourceResult->status() !== SalesCreditSourceStatus::Success || $source === null
                    || ! in_array($source->status(), [SalesInvoiceStatus::Posted, SalesInvoiceStatus::Paid], true)
                    || ! $source->currency()->equals($credit->currency())) {
                    return PostSalesCreditInvoiceResult::forStatus(PostSalesCreditInvoiceStatus::SourceFinancialStateInvalid);
                }
                $creditCustomer = $credit->customerSnapshot();
                $sourceCustomer = $source->customerSnapshot();
                if ($creditCustomer === null || $sourceCustomer === null || ! $creditCustomer->relationId()->equals($sourceCustomer->relationId())) {
                    return PostSalesCreditInvoiceResult::forStatus(PostSalesCreditInvoiceStatus::SourceFinancialStateInvalid);
                }
                $sourcePosting = $this->sourcePostings->findForInvoice($administrationId, $source->id());
                if ($sourcePosting === null) {
                    return PostSalesCreditInvoiceResult::forStatus(PostSalesCreditInvoiceStatus::SourceFinancialStateInvalid);
                }
                $sourceOpenItem = $this->openItemMatches->findLocked($administrationId, $sourcePosting->openItemId());
                if ($sourceOpenItem === null || $sourceOpenItem->type() !== OpenItemType::Receivable || $sourceOpenItem->side() !== OpenItemSide::Debit
                    || ! $sourceOpenItem->journalEntryId()->equals($sourcePosting->journalEntryId())
                    || ! $sourceOpenItem->relationId()->equals($creditCustomer->relationId())
                    || ! $sourceOpenItem->originalAmount()->currency()->equals($credit->currency())) {
                    return PostSalesCreditInvoiceResult::forStatus(PostSalesCreditInvoiceStatus::SourceFinancialStateInvalid);
                }

                $configurationResult = $this->configurationReader->read($administrationId);
                if ($configurationResult->status() === SalesPostingConfigurationReadStatus::Missing) {
                    return PostSalesCreditInvoiceResult::forStatus(PostSalesCreditInvoiceStatus::ConfigurationMissing);
                }
                if ($configurationResult->status() === SalesPostingConfigurationReadStatus::InvalidReference) {
                    return PostSalesCreditInvoiceResult::forStatus(PostSalesCreditInvoiceStatus::ConfigurationInvalid);
                }
                $configuration = $configurationResult->configuration();
                if ($configuration === null) {
                    return PostSalesCreditInvoiceResult::forStatus(PostSalesCreditInvoiceStatus::FinancialStateInconsistent);
                }

                $originals = [];
                foreach ($sourceResult->originalTaxPostings() as $original) {
                    if (! $original->journalEntryId()->equals($sourcePosting->journalEntryId())) {
                        return PostSalesCreditInvoiceResult::forStatus(PostSalesCreditInvoiceStatus::SourceFinancialStateInvalid);
                    }
                    $originals[$original->sourceLineId()->toString()] = $original;
                }
                $fiscalLines = [];
                foreach ($credit->lines() as $line) {
                    $original = $originals[$line->id()->toString()] ?? null;
                    if ($original === null) {
                        return PostSalesCreditInvoiceResult::forStatus(PostSalesCreditInvoiceStatus::SourceFinancialStateInvalid);
                    }
                    $fiscalLines[] = new SalesCreditFiscalLineInput(
                        $line->id(), $original, $configuration->revenueLedgerAccountId(), $configuration->outputVatLedgerAccountId(),
                        $this->identities->journalEntryLineId(), $original->taxAmount()->isZero() ? null : $this->identities->journalEntryLineId(),
                        $this->identities->taxPostingId(),
                    );
                }
                $postingDate = new PostingDate($credit->creditInvoiceDate());
                $period = $this->periodGuard->lockForPosting($administrationId, $postingDate);
                if ($period->status !== AccountingPeriodPostingDecisionStatus::Open) {
                    return PostSalesCreditInvoiceResult::forStatus(match ($period->status) {
                        AccountingPeriodPostingDecisionStatus::Closed => PostSalesCreditInvoiceStatus::PeriodClosed,
                        AccountingPeriodPostingDecisionStatus::NoPeriod => PostSalesCreditInvoiceStatus::NoAccountingPeriod,
                        AccountingPeriodPostingDecisionStatus::IntegrityFailure => PostSalesCreditInvoiceStatus::PeriodIntegrityFailure,
                        AccountingPeriodPostingDecisionStatus::Open => throw new \LogicException,
                    });
                }
                $debtorLineId = $this->identities->journalEntryLineId();
                $fiscalResult = $this->fiscalPosting->execute(
                    $credit, $fiscalLines, $sourceResult->originalTaxPostings(), $configuration->salesJournalId(),
                    $configuration->accountsReceivableLedgerAccountId(), $debtorLineId, $postingDate,
                    new JournalEntryReference($credit->number()->value()),
                );
                $journalEntry = $fiscalResult->postingResult()->journalEntry();
                $gross = $journalEntry?->line($debtorLineId)?->credit();
                if ($journalEntry === null || $gross === null) {
                    throw new SalesCreditInvoicePostingTransactionFailed;
                }
                $openItem = new OpenItem(
                    $this->identities->openItemId(), $administrationId, $creditCustomer->relationId(), $journalEntry->id(),
                    $configuration->accountsReceivableLedgerAccountId(), OpenItemType::Receivable, $gross, $postingDate, OpenItemSide::Credit,
                );
                $this->journalEntries->append($journalEntry);
                foreach ($fiscalResult->taxPostings() as $taxPosting) {
                    $this->taxPostings->append($taxPosting);
                }
                $this->openItems->append($openItem);
                $match = $this->matching->executeAvailable($administrationId, $sourceOpenItem->id(), $openItem->id(), $postingDate, $journalEntry->id());
                if (! in_array($match->status, [MatchOpenItemsStatus::Success, MatchOpenItemsStatus::NothingToMatch], true)) {
                    throw new SalesCreditInvoicePostingTransactionFailed;
                }
                if ($this->postingRepository->append(new SalesCreditInvoicePosting(
                    $administrationId, $creditInvoiceId, $journalEntry->id(), $openItem->id(), $this->clock->now(),
                )) !== SalesCreditInvoicePostingAppendResult::Appended) {
                    throw new SalesCreditInvoicePostingTransactionFailed;
                }
                $credit->post();
                if ($this->creditUpdater->update($administrationId, $credit) !== SalesCreditInvoiceWriteResult::Success) {
                    throw new SalesCreditInvoicePostingTransactionFailed;
                }

                return PostSalesCreditInvoiceResult::success($journalEntry->id(), $openItem->id(), array_map(static fn ($posting) => $posting->id(), $fiscalResult->taxPostings()));
            });
        } catch (Throwable) {
            return PostSalesCreditInvoiceResult::forStatus(PostSalesCreditInvoiceStatus::PostingFailure);
        }
    }
}

final class SalesCreditInvoicePostingTransactionFailed extends RuntimeException {}
