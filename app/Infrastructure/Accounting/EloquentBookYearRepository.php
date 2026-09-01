<?php

declare(strict_types=1);

namespace App\Infrastructure\Accounting;

use App\Application\Accounting\AccountingPeriodHistoryEntry;
use App\Application\Accounting\AccountingPeriodHistoryReadModel;
use App\Application\Accounting\AccountingPeriodHistoryReadRepository;
use App\Application\Accounting\AccountingPeriodLockMode;
use App\Application\Accounting\AccountingPeriodLookupRepository;
use App\Application\Accounting\AccountingPeriodLookupResult;
use App\Application\Accounting\BookYearRepository;
use App\Application\Accounting\PeriodTransitionResult;
use App\Domain\Accounting\Entities\AccountingPeriod;
use App\Domain\Accounting\Entities\BookYear;
use App\Domain\Accounting\Enums\AccountingPeriodStatus;
use App\Domain\Accounting\ValueObjects\AccountingPeriodId;
use App\Domain\Accounting\ValueObjects\BookYearId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EloquentBookYearRepository implements AccountingPeriodHistoryReadRepository, AccountingPeriodLookupRepository, BookYearRepository
{
    public function updateLabel(AdministrationId $a, BookYearId $id, string $label): bool
    {
        return DB::table('book_years')->where('administration_id', $a->toString())->where('id', $id->toString())->update(['label' => $label, 'updated_at' => now()]) === 1;
    }

    public function lockAdministration(AdministrationId $a): void
    {
        DB::table('administrations')->where('id', $a->toString())->lockForUpdate()->first();
    }

    public function overlaps(AdministrationId $a, DateTimeImmutable $s, DateTimeImmutable $e, ?BookYearId $x = null): bool
    {
        $q = DB::table('book_years')->where('administration_id', $a->toString())->whereDate('start_date', '<=', $e)->whereDate('end_date', '>=', $s);
        if ($x) {
            $q->where('id', '!=', $x->toString());
        }

        return $q->exists();
    }

    public function save(BookYear $y): bool
    {
        $now = now();

        return DB::transaction(function () use ($y, $now) {
            $this->lockAdministration($y->administrationId());
            if ($this->overlaps($y->administrationId(), $y->startDate(), $y->endDate(), $y->id())) {
                return false;
            }DB::table('book_years')->upsert([['id' => $y->id()->toString(), 'administration_id' => $y->administrationId()->toString(), 'code' => $y->code(), 'label' => $y->label(), 'start_date' => $y->startDate(), 'end_date' => $y->endDate(), 'created_at' => $now, 'updated_at' => $now]], ['id'], ['label', 'updated_at']);
            foreach ($y->periods() as $p) {
                DB::table('accounting_periods')->insert(['id' => $p->id()->toString(), 'administration_id' => $y->administrationId()->toString(), 'book_year_id' => $y->id()->toString(), 'code' => $p->code(), 'label' => $p->label(), 'start_date' => $p->startDate(), 'end_date' => $p->endDate(), 'status' => $p->status()->value, 'created_at' => $now, 'updated_at' => $now]);
            }

            return true;
        });
    }

    public function find(AdministrationId $a, BookYearId $id, bool $lock = false): ?BookYear
    {
        $q = DB::table('book_years')->where('administration_id', $a->toString())->where('id', $id->toString());
        if ($lock) {
            $q->lockForUpdate();
        }$r = $q->first();
        if (! $r) {
            return null;
        }$ps = DB::table('accounting_periods')->where('administration_id', $a->toString())->where('book_year_id', $id->toString())->orderBy('start_date')->get()->map(fn ($p) => new AccountingPeriod(new AccountingPeriodId(new Uuid($p->id)), $a, $id, $p->code, $p->label, new DateTimeImmutable($p->start_date), new DateTimeImmutable($p->end_date), AccountingPeriodStatus::from($p->status)))->all();

        return new BookYear($id, $a, $r->code, $r->label, new DateTimeImmutable($r->start_date), new DateTimeImmutable($r->end_date), $ps);
    }

    public function allForAdministration(AdministrationId $a): array
    {
        return DB::table('book_years')->where('administration_id', $a->toString())->orderBy('start_date')->pluck('id')
            ->map(fn (string $id): ?BookYear => $this->find($a, new BookYearId(new Uuid($id))))
            ->filter()->values()->all();
    }

    public function savePeriod(AccountingPeriod $period): bool
    {
        if (DB::table('accounting_periods')->where('administration_id', $period->administrationId()->toString())
            ->where('book_year_id', $period->bookYearId()->toString())
            ->whereDate('start_date', '<=', $period->endDate())->whereDate('end_date', '>=', $period->startDate())->exists()) {
            return false;
        }

        return DB::table('accounting_periods')->insertOrIgnore([
            'id' => $period->id()->toString(), 'administration_id' => $period->administrationId()->toString(),
            'book_year_id' => $period->bookYearId()->toString(), 'code' => $period->code(), 'label' => $period->label(),
            'start_date' => $period->startDate(), 'end_date' => $period->endDate(), 'status' => $period->status()->value,
            'created_at' => now(), 'updated_at' => now(),
        ]) === 1;
    }

    public function get(AdministrationId $administrationId, AccountingPeriodId $id): ?AccountingPeriodHistoryReadModel
    {
        $period = DB::table('accounting_periods')->where('administration_id', $administrationId->toString())->where('id', $id->toString())->first();
        if ($period === null) {
            return null;
        }
        $history = DB::table('accounting_period_status_history')->where('administration_id', $administrationId->toString())
            ->where('accounting_period_id', $id->toString())->orderBy('occurred_at')->orderBy('id')->get()
            ->map(fn ($row): AccountingPeriodHistoryEntry => new AccountingPeriodHistoryEntry(
                AccountingPeriodStatus::from($row->from_status), AccountingPeriodStatus::from($row->to_status), $row->reason,
                new UserId(new Uuid($row->actor_id)), new DateTimeImmutable($row->occurred_at),
            ))->all();

        return new AccountingPeriodHistoryReadModel($id, AccountingPeriodStatus::from($period->status), $history);
    }

    public function historicalPostingDates(AdministrationId $a): array
    {
        return DB::table('journal_entries')->where('administration_id', $a->toString())->distinct()->orderBy('posting_date')->pluck('posting_date')->all();
    }

    public function uncoveredPostingDates(AdministrationId $a): array
    {
        return array_values(array_filter($this->historicalPostingDates($a), fn ($d) => DB::table('accounting_periods')->where('administration_id', $a->toString())->whereDate('start_date', '<=', $d)->whereDate('end_date', '>=', $d)->count() !== 1));
    }

    public function forPostingDate(AdministrationId $administrationId, DateTimeImmutable $postingDate, AccountingPeriodLockMode $lockMode = AccountingPeriodLockMode::None): AccountingPeriodLookupResult
    {
        $query = DB::table('accounting_periods')->where('administration_id', $administrationId->toString())
            ->whereDate('start_date', '<=', $postingDate)->whereDate('end_date', '>=', $postingDate)->orderBy('id');
        if ($lockMode === AccountingPeriodLockMode::Shared) {
            $query->sharedLock();
        } elseif ($lockMode === AccountingPeriodLockMode::Exclusive) {
            $query->lockForUpdate();
        }
        $periods = $query->limit(2)->get();
        if ($periods->isEmpty()) {
            return AccountingPeriodLookupResult::noPeriod();
        }
        if ($periods->count() !== 1) {
            return AccountingPeriodLookupResult::integrityFailure();
        }
        $period = $periods->first();

        return AccountingPeriodLookupResult::found(new BookYearId(new Uuid($period->book_year_id)), new AccountingPeriodId(new Uuid($period->id)), AccountingPeriodStatus::from($period->status));
    }

    public function transition(AdministrationId $a, AccountingPeriodId $id, string $reason, UserId $actor, DateTimeImmutable $at, bool $reopen = false): PeriodTransitionResult
    {
        return DB::transaction(function () use ($a, $id, $reason, $actor, $at, $reopen) {
            $p = DB::table('accounting_periods')->where('administration_id', $a->toString())->where('id', $id->toString())->lockForUpdate()->first();
            $from = $reopen ? 'closed' : 'open';
            $to = $reopen ? 'open' : 'closed';
            if (! $p) {
                return PeriodTransitionResult::NotFound;
            }
            if ($p->status !== $from || trim($reason) === '' || mb_strlen(trim($reason)) > 500) {
                return PeriodTransitionResult::InvalidState;
            }DB::table('accounting_periods')->where('id', $p->id)->update(['status' => $to, 'updated_at' => $at]);
            DB::table('accounting_period_status_history')->insert(['id' => (string) Str::uuid(), 'administration_id' => $a->toString(), 'book_year_id' => $p->book_year_id, 'accounting_period_id' => $p->id, 'from_status' => $from, 'to_status' => $to, 'reason' => trim($reason), 'actor_id' => $actor->toString(), 'occurred_at' => $at]);

            return PeriodTransitionResult::Success;
        });
    }
}
