<?php

declare(strict_types=1);

namespace App\Infrastructure\Banking;

use App\Application\Banking\BankTransactionPosting;
use App\Application\Banking\BankTransactionPostingRepository;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Banking\ValueObjects\BankTransactionPostingId;
use App\Domain\Banking\ValueObjects\PaymentAllocationId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class EloquentBankTransactionPostingRepository implements BankTransactionPostingRepository
{
    public function exists(AdministrationId $a, BankTransactionId $id): bool
    {
        return DB::table('bank_transaction_postings')->where('administration_id', $a->toString())->where('bank_transaction_id', $id->toString())->exists();
    }

    public function find(AdministrationId $a, BankTransactionId $id): ?BankTransactionPosting
    {
        $row = DB::table('bank_transaction_postings')->where('administration_id', $a->toString())->where('bank_transaction_id', $id->toString())->first();

        return $row === null ? null : new BankTransactionPosting(
            new BankTransactionPostingId(new Uuid($row->id)),
            new AdministrationId(new Uuid($row->administration_id)),
            new BankTransactionId(new Uuid($row->bank_transaction_id)),
            new JournalEntryId(new Uuid($row->journal_entry_id)),
            new PostingDate(new DateTimeImmutable($row->posting_date)),
        );
    }

    public function settlementAmount(AdministrationId $a, PaymentAllocationId $id): ?Money
    {
        $row = DB::table('open_item_settlements')->where('administration_id', $a->toString())->where('payment_allocation_id', $id->toString())->first();

        return $row === null ? null : new Money((string) $row->amount, new Currency($row->currency));
    }

    public function append(BankTransactionPostingId $id, AdministrationId $a, BankTransactionId $tx, JournalEntryId $e, PostingDate $d): void
    {
        DB::table('bank_transaction_postings')->insert(['id' => $id->toString(), 'administration_id' => $a->toString(), 'bank_transaction_id' => $tx->toString(), 'journal_entry_id' => $e->toString(), 'posting_date' => $d->value()->format('Y-m-d'), 'created_at' => now(), 'updated_at' => now()]);
    }
}
