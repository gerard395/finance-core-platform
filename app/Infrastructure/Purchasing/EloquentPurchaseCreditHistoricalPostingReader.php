<?php

declare(strict_types=1);

namespace App\Infrastructure\Purchasing;

use App\Application\Accounting\OpenItemMatchRepository;
use App\Application\Purchasing\PurchaseCreditHistoricalPosting;
use App\Application\Purchasing\PurchaseCreditHistoricalPostingReader;
use App\Application\Purchasing\PurchaseInvoicePostingRepository;
use App\Domain\Accounting\Enums\OpenItemSide;
use App\Domain\Accounting\Enums\OpenItemType;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Purchasing\Entities\PurchaseCreditInvoice;
use App\Domain\Shared\Identity\Uuid;
use Illuminate\Support\Facades\DB;

final readonly class EloquentPurchaseCreditHistoricalPostingReader implements PurchaseCreditHistoricalPostingReader
{
    public function __construct(private PurchaseInvoicePostingRepository $postings, private OpenItemMatchRepository $openItems) {}

    public function readLocked(AdministrationId $administrationId, PurchaseCreditInvoice $credit): ?PurchaseCreditHistoricalPosting
    {
        $sourceInvoiceId = $credit->sourcePurchaseInvoiceId();
        $sourcePayableId = $credit->sourcePayableOpenItemId();
        if ($sourceInvoiceId === null || $sourcePayableId === null) {
            return null;
        }
        $posting = $this->postings->findForInvoice($administrationId, $sourceInvoiceId);
        if ($posting === null || ! $posting->openItemId->equals($sourcePayableId)) {
            return null;
        }

        // Shared B2/PC order: lock the document before its source payable OpenItem.
        $sourcePayable = $this->openItems->findLocked($administrationId, $posting->openItemId);
        if ($sourcePayable === null || $sourcePayable->type() !== OpenItemType::Payable || $sourcePayable->side() !== OpenItemSide::Credit
            || ! $sourcePayable->relationId()->equals($credit->supplierSnapshot()->relationId)
            || ! $sourcePayable->originalAmount()->currency()->equals($credit->currency())) {
            return null;
        }
        $entry = DB::table('journal_entries')->where('administration_id', $administrationId->toString())
            ->where('id', $posting->journalEntryId->toString())->lockForUpdate()->first(['journal_id']);
        if ($entry === null) {
            return null;
        }

        $vatAccounts = [];
        $taxAccounts = [];
        foreach ($credit->lines() as $line) {
            $account = $line->account();
            $taxPostingId = $line->sourceTaxPostingId();
            if ($account === null || ($taxPostingId === null && $line->internationalTaxSnapshot() === null) || $line->sourcePurchaseInvoiceLineId() === null) {
                return null;
            }
            if (($international = $line->internationalTaxSnapshot()) !== null) {
                foreach ($international->originalTaxPostingIds as $originalId) {
                    $row = DB::table('tax_postings as tax')
                        ->join('journal_entry_lines as base', function ($join): void {
                            $join->on('base.administration_id', '=', 'tax.administration_id')->on('base.journal_entry_id', '=', 'tax.journal_entry_id')->on('base.id', '=', 'tax.base_journal_entry_line_id');
                        })->join('journal_entry_lines as vat', function ($join): void {
                            $join->on('vat.administration_id', '=', 'tax.administration_id')->on('vat.journal_entry_id', '=', 'tax.journal_entry_id')->on('vat.id', '=', 'tax.tax_journal_entry_line_id');
                        })->where('tax.administration_id', $administrationId->toString())->where('tax.id', $originalId->toString())
                        ->where('tax.journal_entry_id', $posting->journalEntryId->toString())->where('tax.type', 'original')
                        ->where('tax.source_document_type', 'purchase_invoice')->where('tax.source_document_id', $sourceInvoiceId->toString())
                        ->where('tax.source_line_id', $line->sourcePurchaseInvoiceLineId()->toString())
                        ->where('tax.tax_treatment_group_id', $international->sourceGroupId->toString())
                        ->first(['base.ledger_account_id as base_account_id', 'base.debit_amount as base_debit_amount', 'vat.ledger_account_id as vat_account_id']);
                    if ($row === null || $row->base_account_id !== $account->id->toString()
                        || (string) $row->base_debit_amount !== $line->net()->add($international->nonDeductibleTaxCost)->amount()) {
                        return null;
                    }
                    $taxAccounts[$originalId->toString()] = new LedgerAccountId(new Uuid($row->vat_account_id));
                }

                continue;
            }
            $row = DB::table('tax_postings as tax')
                ->join('journal_entry_lines as base', function ($join): void {
                    $join->on('base.administration_id', '=', 'tax.administration_id')->on('base.journal_entry_id', '=', 'tax.journal_entry_id')->on('base.id', '=', 'tax.base_journal_entry_line_id');
                })->leftJoin('journal_entry_lines as vat', function ($join): void {
                    $join->on('vat.administration_id', '=', 'tax.administration_id')->on('vat.journal_entry_id', '=', 'tax.journal_entry_id')->on('vat.id', '=', 'tax.tax_journal_entry_line_id');
                })->where('tax.administration_id', $administrationId->toString())->where('tax.id', $taxPostingId->toString())
                ->where('tax.journal_entry_id', $posting->journalEntryId->toString())->where('tax.direction', 'input')->where('tax.type', 'original')
                ->where('tax.source_document_type', 'purchase_invoice')->where('tax.source_document_id', $sourceInvoiceId->toString())
                ->where('tax.source_line_id', $line->sourcePurchaseInvoiceLineId()->toString())
                ->first(['tax.tax_amount', 'tax.taxable_base', 'tax.tax_journal_entry_line_id', 'base.ledger_account_id as base_account_id', 'base.debit_amount as base_debit_amount', 'vat.ledger_account_id as vat_account_id', 'vat.debit_amount as vat_debit_amount']);
            if ($row === null || $row->base_account_id !== $account->id->toString() || (string) $row->taxable_base !== $line->net()->amount()
                || (string) $row->base_debit_amount !== $line->net()->amount() || (string) $row->tax_amount !== $line->taxAmount()->amount()) {
                return null;
            }
            if ($line->taxAmount()->isPositive()) {
                if ($row->tax_journal_entry_line_id === null || $row->vat_account_id === null || (string) $row->vat_debit_amount !== $line->taxAmount()->amount()) {
                    return null;
                }
                $vatAccounts[$line->id()->toString()] = new LedgerAccountId(new Uuid($row->vat_account_id));
            } else {
                if ($row->tax_journal_entry_line_id !== null || $row->vat_account_id !== null) {
                    return null;
                }
                $vatAccounts[$line->id()->toString()] = null;
            }
        }

        return new PurchaseCreditHistoricalPosting($posting->journalEntryId, new JournalId(new Uuid($entry->journal_id)), $sourcePayable, $vatAccounts, $taxAccounts);
    }
}
