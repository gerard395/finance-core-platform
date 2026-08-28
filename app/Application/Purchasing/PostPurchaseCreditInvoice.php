<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Application\Accounting\JournalEntryStore;
use App\Application\Accounting\OpenItemMatchAppendResult;
use App\Application\Accounting\OpenItemMatchIdentityGenerator;
use App\Application\Accounting\OpenItemMatchRepository;
use App\Application\Accounting\OpenItemStore;
use App\Application\Fiscal\TaxPostingReadRepository;
use App\Application\Fiscal\TaxPostingStore;
use App\Application\Shared\TransactionManager;
use App\Domain\Accounting\Entities\OpenItem;
use App\Domain\Accounting\Enums\OpenItemSide;
use App\Domain\Accounting\Enums\OpenItemType;
use App\Domain\Accounting\Services\OpenItemMatchingPolicy;
use App\Domain\Accounting\ValueObjects\JournalEntryReference;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Enums\TaxSourceDocumentType;
use App\Domain\Fiscal\ValueObjects\TaxSourceDocumentId;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Purchasing\Enums\PurchaseCreditInvoiceStatus;
use App\Domain\Purchasing\Enums\PurchaseInvoiceStatus;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceId;
use App\Domain\Shared\Finance\Money;
use Throwable;

final readonly class PostPurchaseCreditInvoice
{
    public function __construct(private TransactionManager $transactions, private PurchaseCreditInvoiceRepository $credits, private PurchaseInvoiceRepository $invoices, private PurchaseCreditHistoricalPostingReader $history, private PurchaseCreditPostingRepository $postings, private TaxPostingReadRepository $taxReads, private PostPurchaseCreditInvoiceWithTax $fiscal, private JournalEntryStore $journals, private TaxPostingStore $taxStore, private OpenItemStore $openItems, private OpenItemMatchRepository $matches, private OpenItemMatchIdentityGenerator $matchIds, private OpenItemMatchingPolicy $matchingPolicy, private PurchaseCreditIdentityGenerator $ids, private PurchaseCreditClock $clock) {}

    public function execute(AdministrationId $admin, PurchaseCreditInvoiceId $id, PostingDate $postingDate, UserId $actor): PostPurchaseCreditInvoiceResult
    {
        try {
            return $this->transactions->run(function () use ($admin, $id, $postingDate, $actor) {
                $credit = $this->credits->findForUpdate($admin, $id);
                if ($credit === null) {
                    return new PostPurchaseCreditInvoiceResult(PostPurchaseCreditInvoiceStatus::NotFound);
                }
                $existing = $this->postings->find($admin, $id);
                if ($existing !== null) {
                    return new PostPurchaseCreditInvoiceResult($credit->status() === PurchaseCreditInvoiceStatus::Posted ? PostPurchaseCreditInvoiceStatus::AlreadyPosted : PostPurchaseCreditInvoiceStatus::FinancialStateInvalid);
                }
                if ($credit->status() === PurchaseCreditInvoiceStatus::Posted) {
                    return new PostPurchaseCreditInvoiceResult(PostPurchaseCreditInvoiceStatus::FinancialStateInvalid);
                }
                if ($credit->status() !== PurchaseCreditInvoiceStatus::Finalized) {
                    return new PostPurchaseCreditInvoiceResult(PostPurchaseCreditInvoiceStatus::InvalidState);
                }
                $sourceId = $credit->sourcePurchaseInvoiceId();
                $source = $sourceId === null ? null : $this->invoices->findForUpdate($admin, $sourceId);
                if ($source === null || $source->status() !== PurchaseInvoiceStatus::Posted || ! $source->supplierId()->equals($credit->supplierId()) || ! $source->supplierSnapshot()->relationId->equals($credit->supplierSnapshot()->relationId) || ! $source->currency()->equals($credit->currency())) {
                    return new PostPurchaseCreditInvoiceResult(PostPurchaseCreditInvoiceStatus::FinancialStateInvalid);
                }
                $historical = $this->history->readLocked($admin, $credit);
                if ($historical === null) {
                    return new PostPurchaseCreditInvoiceResult(PostPurchaseCreditInvoiceStatus::FinancialStateInvalid);
                }
                $originals = $this->taxReads->findOriginalsForSource($admin, TaxSourceDocumentType::PurchaseInvoice, new TaxSourceDocumentId($sourceId->uuid()));
                $byId = [];
                foreach ($originals as $original) {
                    $byId[$original->id()->toString()] = $original;
                }
                $fiscalLines = [];
                $claims = [];
                $now = $this->clock->now();
                $ordered = $credit->lines();
                usort($ordered, fn ($a, $b) => strcmp($a->sourcePurchaseInvoiceLineId()->toString(), $b->sourcePurchaseInvoiceLineId()->toString()));
                foreach ($ordered as $line) {
                    $taxId = $line->sourceTaxPostingId();
                    $original = $taxId === null ? null : ($byId[$taxId->toString()] ?? null);
                    $account = $line->account();
                    $vat = $historical->vatAccounts[$line->id()->toString()] ?? null;
                    if ($original === null || $account === null || ($line->taxAmount()->isPositive() && $vat === null)) {
                        return new PostPurchaseCreditInvoiceResult(PostPurchaseCreditInvoiceStatus::FinancialStateInvalid);
                    }
                    $fiscalLines[] = new PurchasingCreditFiscalLineInput($line->id(), $original, $account->id, $vat ?? $account->id, $this->ids->journalEntryLineId(), $line->taxAmount()->isZero() ? null : $this->ids->journalEntryLineId(), $this->ids->taxPostingId());
                    $claims[] = new PurchaseCreditSourceLineClaim($this->ids->claimId(), $admin, $line->sourcePurchaseInvoiceLineId(), $id, $line->id(), $now);
                }
                if (! $this->postings->appendClaims($claims)) {
                    throw new SourceLineClaimConflict;
                }
                $apLine = $this->ids->journalEntryLineId();
                $result = $this->fiscal->execute($credit, $fiscalLines, $originals, $historical->journalId, $historical->sourcePayable->controlLedgerAccountId(), $apLine, $postingDate, new JournalEntryReference($credit->number()->value()));
                $entry = $result->postingResult()->journalEntry();
                if ($entry === null) {
                    throw new \RuntimeException('PostingEngine rejected purchase credit reversal.');
                }
                $open = new OpenItem($this->ids->openItemId(), $admin, $historical->sourcePayable->relationId(), $entry->id(), $historical->sourcePayable->controlLedgerAccountId(), OpenItemType::Payable, $credit->grossTotal(), $postingDate, OpenItemSide::Debit, null);
                $this->journals->append($entry);
                foreach ($result->taxPostings() as $tax) {
                    $this->taxStore->append($tax);
                }
                $this->openItems->append($open);
                $pair = $this->matches->findLockedPair($admin, $open->id(), $historical->sourcePayable->id());
                if ($pair === null) {
                    throw new \RuntimeException('Purchase credit matching OpenItems are unavailable.');
                }
                $sourceOpen = $pair->credit->openAmount();
                $creditOpen = $pair->debit->openAmount();
                $matched = Money::zero($credit->currency());
                if (! $sourceOpen->isZero() && ! $creditOpen->isZero()) {
                    $matched = $sourceOpen->subtract($creditOpen)->isPositive() ? $creditOpen : $sourceOpen;
                    $match = $this->matchingPolicy->create($this->matchIds->next(), $pair->debit, $pair->credit, $matched, $postingDate, $entry->id());
                    if ($this->matches->appendMatch($match) !== OpenItemMatchAppendResult::Appended) {
                        throw new \RuntimeException('Purchase credit automatic match persistence failed.');
                    }
                }
                $sourceRemaining = $sourceOpen->subtract($matched);
                $creditRemaining = $creditOpen->subtract($matched);
                if (! $this->postings->append(new PurchaseCreditPosting($this->ids->postingId(), $admin, $id, $entry->id(), $open->id(), $postingDate, $now))) {
                    throw new \RuntimeException('Purchase credit posting linkage conflict.');
                }
                $credit->post($actor, $this->clock->now());
                if (! $this->credits->save($credit)) {
                    throw new \RuntimeException('Purchase credit status persistence failed.');
                }

                return new PostPurchaseCreditInvoiceResult(PostPurchaseCreditInvoiceStatus::Success, $entry->id(), $open->id(), array_map(fn ($t) => $t->id(), $result->taxPostings()), $historical->sourcePayable->id(), $matched, $sourceRemaining, $creditRemaining);
            });
        } catch (SourceLineClaimConflict) {
            return new PostPurchaseCreditInvoiceResult(PostPurchaseCreditInvoiceStatus::SourceLineAlreadyCredited);
        } catch (Throwable) {
            return new PostPurchaseCreditInvoiceResult(PostPurchaseCreditInvoiceStatus::PostingFailure);
        }
    }
}
final class SourceLineClaimConflict extends \RuntimeException {}
