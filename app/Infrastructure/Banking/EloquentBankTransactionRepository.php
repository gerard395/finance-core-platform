<?php

declare(strict_types=1);

namespace App\Infrastructure\Banking;

use App\Application\Banking\BankTransactionRepository;
use App\Domain\Accounting\Enums\OpenItemSide;
use App\Domain\Accounting\Enums\OpenItemType;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\BankTransaction;
use App\Domain\Banking\Entities\OtherBankTransactionIntent;
use App\Domain\Banking\Entities\Payment;
use App\Domain\Banking\Entities\PaymentAllocation;
use App\Domain\Banking\Enums\BankTransactionStatus;
use App\Domain\Banking\Enums\PaymentType;
use App\Domain\Banking\ValueObjects\AdministrationBankAccountId;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Banking\ValueObjects\BankTransactionReference;
use App\Domain\Banking\ValueObjects\PaymentAllocationId;
use App\Domain\Banking\ValueObjects\PaymentId;
use App\Domain\Banking\ValueObjects\TransactionDate;
use App\Domain\Banking\ValueObjects\TransactionDescription;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class EloquentBankTransactionRepository implements BankTransactionRepository
{
    public function save(BankTransaction $tx): void
    {
        DB::table('bank_transactions')->updateOrInsert(['id' => $tx->id()->toString()], ['administration_id' => $tx->administrationId()->toString(), 'administration_bank_account_id' => $tx->bankAccountId()->toString(), 'transaction_date' => $tx->transactionDate()->value()->format('Y-m-d'), 'amount' => $tx->amount()->amount(), 'currency' => $tx->amount()->currency()->code(), 'reference' => $tx->reference()->value(), 'description' => $tx->description()->value(), 'status' => $tx->status()->value, 'created_by' => $tx->createdBy()->toString(), 'created_at' => $tx->createdAt(), 'finalized_by' => $tx->finalizedBy()?->toString(), 'finalized_at' => $tx->finalizedAt(), 'posted_by' => $tx->postedBy()?->toString(), 'posted_at' => $tx->postedAt()]);
        $p = $tx->paymentOrNull();
        $other = $tx->otherIntentOrNull();
        if (($p !== null) === ($other !== null)) {
            throw new DomainException('Bank transaction must have exactly one financial intent.');
        }
        if ($other !== null) {
            if (DB::table('payments')->where('administration_id', $tx->administrationId()->toString())->where('bank_transaction_id', $tx->id()->toString())->exists()) {
                throw new DomainException('Payment and Other intent cannot coexist.');
            }
            DB::table('other_bank_transaction_intents')->updateOrInsert(['administration_id' => $tx->administrationId()->toString(), 'bank_transaction_id' => $tx->id()->toString()], ['contra_ledger_account_id' => $other->contraLedgerAccountId()->toString(), 'amount' => $other->amount()->amount(), 'currency' => $other->amount()->currency()->code()]);

            return;
        }
        if (DB::table('other_bank_transaction_intents')->where('administration_id', $tx->administrationId()->toString())->where('bank_transaction_id', $tx->id()->toString())->exists()) {
            throw new DomainException('Payment and Other intent cannot coexist.');
        }
        DB::table('payments')->updateOrInsert(['id' => $p->id()->toString()], ['administration_id' => $tx->administrationId()->toString(), 'bank_transaction_id' => $tx->id()->toString(), 'relation_id' => $p->relationId()->toString(), 'type' => $p->type()->value, 'amount' => $p->amount()->amount(), 'currency' => $p->amount()->currency()->code()]);
        $ids = [];
        foreach ($p->allocations() as $a) {
            $ids[] = $a->id()->toString();
            DB::table('payment_allocations')->updateOrInsert(['id' => $a->id()->toString()], ['administration_id' => $tx->administrationId()->toString(), 'payment_id' => $p->id()->toString(), 'open_item_id' => $a->openItemId()->toString(), 'amount' => $a->amount()->amount(), 'currency' => $a->amount()->currency()->code(), 'open_item_type' => $a->openItemType()?->value, 'open_item_side' => $a->openItemSide()?->value, 'relation_id_snapshot' => $a->relationId()?->toString(), 'control_ledger_account_id_snapshot' => $a->controlLedgerAccountId()?->toString()]);
        } $q = DB::table('payment_allocations')->where('administration_id', $tx->administrationId()->toString())->where('payment_id', $p->id()->toString());
        $ids === [] ? $q->delete() : $q->whereNotIn('id', $ids)->delete();
    }

    public function find(AdministrationId $admin, BankTransactionId $id, bool $forUpdate = false): ?BankTransaction
    {
        $q = DB::table('bank_transactions')->where('administration_id', $admin->toString())->where('id', $id->toString());
        if ($forUpdate) {
            $q->lockForUpdate();
        }$r = $q->first();

        return $r === null ? null : $this->hydrate($r);
    }

    public function list(AdministrationId $admin): array
    {
        return DB::table('bank_transactions')->where('administration_id', $admin->toString())->orderByDesc('transaction_date')->orderBy('id')->get()->map(fn ($r) => $this->hydrate($r))->all();
    }

    private function hydrate(object $r): BankTransaction
    {
        $p = DB::table('payments')->where('administration_id', $r->administration_id)->where('bank_transaction_id', $r->id)->first();
        $other = DB::table('other_bank_transaction_intents')->where('administration_id', $r->administration_id)->where('bank_transaction_id', $r->id)->first();
        if (($p !== null) === ($other !== null)) {
            throw new DomainException('Persisted bank transaction must have exactly one financial intent.');
        }
        if ($other !== null) {
            $intent = new OtherBankTransactionIntent(new LedgerAccountId(new Uuid($other->contra_ledger_account_id)), new Money($other->amount, new Currency($other->currency)));
        } else {
            $alloc = DB::table('payment_allocations')->where('administration_id', $r->administration_id)->where('payment_id', $p->id)->orderBy('id')->get()->map(fn ($a) => new PaymentAllocation(new PaymentAllocationId(new Uuid($a->id)), new OpenItemId(new Uuid($a->open_item_id)), new Money($a->amount, new Currency($a->currency)), $a->open_item_type === null ? null : OpenItemType::from($a->open_item_type), $a->open_item_side === null ? null : OpenItemSide::from($a->open_item_side), $a->relation_id_snapshot === null ? null : new RelationId(new Uuid($a->relation_id_snapshot)), $a->control_ledger_account_id_snapshot === null ? null : new LedgerAccountId(new Uuid($a->control_ledger_account_id_snapshot))))->all();
            $intent = new Payment(new PaymentId(new Uuid($p->id)), PaymentType::from($p->type), new RelationId(new Uuid($p->relation_id)), new Money($p->amount, new Currency($p->currency)), $alloc);
        }

        return new BankTransaction(new BankTransactionId(new Uuid($r->id)), new AdministrationBankAccountId(new Uuid($r->administration_bank_account_id)), new AdministrationId(new Uuid($r->administration_id)), new TransactionDate(new DateTimeImmutable($r->transaction_date)), new Money($r->amount, new Currency($r->currency)), new BankTransactionReference($r->reference), new TransactionDescription($r->description), $intent, BankTransactionStatus::from($r->status), new UserId(new Uuid($r->created_by)), new DateTimeImmutable($r->created_at), $r->finalized_by === null ? null : new UserId(new Uuid($r->finalized_by)), $r->finalized_at === null ? null : new DateTimeImmutable($r->finalized_at), $r->posted_by === null ? null : new UserId(new Uuid($r->posted_by)), $r->posted_at === null ? null : new DateTimeImmutable($r->posted_at));
    }
}
