<?php

declare(strict_types=1);

namespace App\Infrastructure\Banking;

use App\Application\Banking\BankTransactionPosting;
use App\Application\Banking\BankTransactionRepository;
use App\Application\Banking\BankTransactionReversalRepository;
use App\Application\Banking\BankTransactionReversalSettlementSource;
use App\Application\Banking\BankTransactionReversalSource;
use App\Application\Banking\BankTransactionReversalSourceReader;
use App\Application\Banking\BankTransactionSettlementReversalLinkRepository;
use App\Domain\Accounting\Entities\JournalEntry;
use App\Domain\Accounting\Entities\JournalEntryLine;
use App\Domain\Accounting\Entities\OpenItemSettlement;
use App\Domain\Accounting\Enums\JournalEntryStatus;
use App\Domain\Accounting\Enums\OpenItemSettlementType;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\JournalEntryReference;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Accounting\ValueObjects\OpenItemSettlementId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\BankTransactionReversal;
use App\Domain\Banking\Entities\BankTransactionSettlementReversalLink;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Banking\ValueObjects\BankTransactionPostingId;
use App\Domain\Banking\ValueObjects\BankTransactionReversalId;
use App\Domain\Banking\ValueObjects\BankTransactionReversalReason;
use App\Domain\Banking\ValueObjects\BankTransactionSettlementReversalLinkId;
use App\Domain\Banking\ValueObjects\PaymentAllocationId;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final readonly class EloquentBankTransactionReversalRepository implements BankTransactionReversalRepository, BankTransactionReversalSourceReader, BankTransactionSettlementReversalLinkRepository
{
    public function __construct(private BankTransactionRepository $transactions) {}

    public function findByOriginal(AdministrationId $administrationId, BankTransactionId $bankTransactionId, bool $forUpdate = false): ?BankTransactionReversal
    {
        $query = DB::table('bank_transaction_reversals')->where('administration_id', $administrationId->toString())->where('original_bank_transaction_id', $bankTransactionId->toString());
        if ($forUpdate) {
            $query->lockForUpdate();
        }
        $row = $query->first();

        return $row === null ? null : $this->reversal($row);
    }

    public function findByReversal(AdministrationId $administrationId, BankTransactionReversalId $reversalId, bool $forUpdate = false): array
    {
        $query = DB::table('bank_transaction_settlement_reversal_links')->where('administration_id', $administrationId->toString())->where('bank_transaction_reversal_id', $reversalId->toString())->orderBy('payment_allocation_id');
        if ($forUpdate) {
            $query->lockForUpdate();
        }

        return $query->get()->map(fn (object $row) => $this->link($row))->all();
    }

    public function read(AdministrationId $administrationId, BankTransactionId $bankTransactionId, bool $forUpdate = false): ?BankTransactionReversalSource
    {
        $transaction = $this->transactions->find($administrationId, $bankTransactionId, $forUpdate);
        if ($transaction === null) {
            return null;
        }
        $reversal = $this->findByOriginal($administrationId, $bankTransactionId, $forUpdate);
        $payment = $transaction->paymentOrNull();
        if ($forUpdate && $payment !== null) {
            DB::table('payments')->where('administration_id', $administrationId->toString())->where('bank_transaction_id', $bankTransactionId->toString())->lockForUpdate()->get();
            DB::table('payment_allocations')->where('administration_id', $administrationId->toString())->where('payment_id', $payment->id()->toString())->orderBy('id')->lockForUpdate()->get();
        }
        $postingQuery = DB::table('bank_transaction_postings')->where('administration_id', $administrationId->toString())->where('bank_transaction_id', $bankTransactionId->toString());
        if ($forUpdate) {
            $postingQuery->lockForUpdate();
        }
        $postingRow = $postingQuery->first();
        $posting = $postingRow === null ? null : new BankTransactionPosting(new BankTransactionPostingId(new Uuid($postingRow->id)), $administrationId, $bankTransactionId, new JournalEntryId(new Uuid($postingRow->journal_entry_id)), new PostingDate(new DateTimeImmutable($postingRow->posting_date)));
        $journal = $posting === null ? null : $this->journal($administrationId, $posting->journalEntryId, $forUpdate);
        $settlements = [];
        $coherent = $posting !== null && $journal !== null && $journal->isPosted() && $journal->id()->toString() === $posting->journalEntryId->toString();
        foreach ($payment?->allocations() ?? [] as $allocation) {
            $query = DB::table('open_item_settlements')->where('administration_id', $administrationId->toString())->where('payment_allocation_id', $allocation->id()->toString());
            $rows = $query->get();
            if ($rows->count() !== 1) {
                $coherent = false;

                continue;
            }
            $row = $rows->first();
            if ($row->open_item_id !== $allocation->openItemId()->toString() || $row->source_journal_entry_id !== $posting?->journalEntryId->toString()) {
                $coherent = false;
            }
            $hasReversal = DB::table('open_item_settlements')->where('administration_id', $administrationId->toString())->where('reversed_settlement_id', $row->id)->exists();
            $settlements[] = new BankTransactionReversalSettlementSource($allocation->id(), $allocation->openItemId(), $this->settlement($row), $hasReversal);
        }
        if ($reversal !== null) {
            $links = $this->findByReversal($administrationId, $reversal->id, $forUpdate);
            $coherent = $coherent && count($links) === count($settlements) && count($links) === count($payment?->allocations() ?? []);
            $sourceByAllocation = [];
            foreach ($settlements as $source) {
                $sourceByAllocation[$source->paymentAllocationId->toString()] = $source;
            }
            foreach ($links as $link) {
                $source = $sourceByAllocation[$link->paymentAllocationId->toString()] ?? null;
                $reversalSettlement = DB::table('open_item_settlements')->where('administration_id', $administrationId->toString())->where('id', $link->reversalOpenItemSettlementId->toString())->first();
                if ($source === null || $link->openItemId->toString() !== $source->openItemId->toString() || $link->originalOpenItemSettlementId->toString() !== $source->settlement->id()->toString() || $reversalSettlement === null || $reversalSettlement->type !== OpenItemSettlementType::Reversal->value || $reversalSettlement->reversed_settlement_id !== $source->settlement->id()->toString() || $reversalSettlement->source_journal_entry_id !== $reversal->reversalJournalEntryId->toString()) {
                    $coherent = false;
                }
            }
        }
        $other = $transaction->otherIntentOrNull();
        if ($other !== null && $journal !== null) {
            $contraLines = array_values(array_filter(
                $journal->lines(),
                static fn (JournalEntryLine $line): bool => $line->ledgerAccountId()->equals($other->contraLedgerAccountId()),
            ));
            $contraLine = $contraLines[0] ?? null;
            $expectedAmount = $transaction->amount()->absolute();
            $contraAmount = $transaction->amount()->isPositive() ? $contraLine?->credit() : $contraLine?->debit();
            $coherent = $coherent
                && count($journal->lines()) === 2
                && count($contraLines) === 1
                && $contraAmount?->equals($expectedAmount) === true
                && $settlements === [];
        }

        return new BankTransactionReversalSource($transaction, $posting, $journal, $settlements, $reversal, $coherent);
    }

    public function appendReversal(BankTransactionReversal $reversal): bool
    {
        return $this->saveReversal($reversal);
    }

    public function appendLink(BankTransactionSettlementReversalLink $link): bool
    {
        return $this->saveSettlementLink($link);
    }

    private function saveReversal(BankTransactionReversal $reversal): bool
    {
        try {
            DB::table('bank_transaction_reversals')->insert(['id' => $reversal->id->toString(), 'administration_id' => $reversal->administrationId->toString(), 'original_bank_transaction_id' => $reversal->originalBankTransactionId->toString(), 'original_bank_transaction_posting_id' => $reversal->originalBankTransactionPostingId->toString(), 'original_journal_entry_id' => $reversal->originalJournalEntryId->toString(), 'reversal_journal_entry_id' => $reversal->reversalJournalEntryId->toString(), 'reversal_posting_date' => $reversal->reversalPostingDate->value()->format('Y-m-d'), 'reason' => $reversal->reason->value(), 'reversed_by' => $reversal->reversedBy->toString(), 'reversed_at' => $reversal->reversedAt, 'created_at' => $reversal->reversedAt]);

            return true;
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                return false;
            }
            throw $exception;
        }
    }

    private function saveSettlementLink(BankTransactionSettlementReversalLink $link): bool
    {
        try {
            DB::table('bank_transaction_settlement_reversal_links')->insert(['id' => $link->id->toString(), 'administration_id' => $link->administrationId->toString(), 'bank_transaction_reversal_id' => $link->bankTransactionReversalId->toString(), 'payment_allocation_id' => $link->paymentAllocationId->toString(), 'open_item_id' => $link->openItemId->toString(), 'original_open_item_settlement_id' => $link->originalOpenItemSettlementId->toString(), 'reversal_open_item_settlement_id' => $link->reversalOpenItemSettlementId->toString(), 'created_at' => now()]);

            return true;
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                return false;
            }
            throw $exception;
        }
    }

    private function reversal(object $row): BankTransactionReversal
    {
        return new BankTransactionReversal(new BankTransactionReversalId(new Uuid($row->id)), new AdministrationId(new Uuid($row->administration_id)), new BankTransactionId(new Uuid($row->original_bank_transaction_id)), new BankTransactionPostingId(new Uuid($row->original_bank_transaction_posting_id)), new JournalEntryId(new Uuid($row->original_journal_entry_id)), new JournalEntryId(new Uuid($row->reversal_journal_entry_id)), new PostingDate(new DateTimeImmutable($row->reversal_posting_date)), new BankTransactionReversalReason($row->reason), new UserId(new Uuid($row->reversed_by)), new DateTimeImmutable($row->reversed_at));
    }

    private function link(object $row): BankTransactionSettlementReversalLink
    {
        return new BankTransactionSettlementReversalLink(new BankTransactionSettlementReversalLinkId(new Uuid($row->id)), new AdministrationId(new Uuid($row->administration_id)), new BankTransactionReversalId(new Uuid($row->bank_transaction_reversal_id)), new PaymentAllocationId(new Uuid($row->payment_allocation_id)), new OpenItemId(new Uuid($row->open_item_id)), new OpenItemSettlementId(new Uuid($row->original_open_item_settlement_id)), new OpenItemSettlementId(new Uuid($row->reversal_open_item_settlement_id)));
    }

    private function settlement(object $row): OpenItemSettlement
    {
        return new OpenItemSettlement(new OpenItemSettlementId(new Uuid($row->id)), new PostingDate(new DateTimeImmutable($row->effective_date)), new Money($row->amount, new Currency($row->currency)), new JournalEntryId(new Uuid($row->source_journal_entry_id)), OpenItemSettlementType::from($row->type), $row->reversed_settlement_id === null ? null : new OpenItemSettlementId(new Uuid($row->reversed_settlement_id)));
    }

    private function journal(AdministrationId $administrationId, JournalEntryId $id, bool $forUpdate): ?JournalEntry
    {
        $query = DB::table('journal_entries')->where('administration_id', $administrationId->toString())->where('id', $id->toString());
        if ($forUpdate) {
            $query->lockForUpdate();
        }
        $row = $query->first();
        if ($row === null) {
            return null;
        }
        $lines = DB::table('journal_entry_lines')->where('administration_id', $administrationId->toString())->where('journal_entry_id', $id->toString())->orderBy('id')->get()->map(static fn (object $line) => new JournalEntryLine(new JournalEntryLineId(new Uuid($line->id)), new LedgerAccountId(new Uuid($line->ledger_account_id)), $line->debit_amount === null ? null : new Money($line->debit_amount, new Currency($line->currency)), $line->credit_amount === null ? null : new Money($line->credit_amount, new Currency($line->currency)), $line->description))->all();

        return JournalEntry::reconstitute($id, $administrationId, new JournalId(new Uuid($row->journal_id)), new PostingDate(new DateTimeImmutable($row->posting_date)), new JournalEntryReference($row->reference), JournalEntryStatus::from($row->status), $lines);
    }
}
