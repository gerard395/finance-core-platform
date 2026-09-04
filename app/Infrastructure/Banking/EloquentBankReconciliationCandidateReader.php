<?php

declare(strict_types=1);

namespace App\Infrastructure\Banking;

use App\Application\Accounting\OpenItemReadRepository;
use App\Application\Banking\BankReconciliationCandidate;
use App\Application\Banking\BankReconciliationCandidateReader;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Administration\ValueObjects\AdministrationId;
use Illuminate\Support\Facades\DB;

final readonly class EloquentBankReconciliationCandidateReader implements BankReconciliationCandidateReader
{
    public function __construct(private OpenItemReadRepository $openItems) {}

    public function eligible(AdministrationId $administrationId, PostingDate $asOf): array
    {
        $items = array_values(array_filter($this->openItems->findForAdministrationAsOf($administrationId, $asOf), static fn ($item): bool => $item->isOpen() || $item->isPartiallySettled()));
        if ($items === []) {
            return [];
        }
        $relationIds = array_values(array_unique(array_map(static fn ($item): string => $item->relationId()->toString(), $items)));
        $journalIds = array_values(array_unique(array_map(static fn ($item): string => $item->journalEntryId()->toString(), $items)));
        $relations = DB::table('relations')->where('administration_id', $administrationId->toString())->whereIn('id', $relationIds)->where('active', true)->pluck('display_name', 'id');
        $ibans = DB::table('relation_bank_accounts')->where('administration_id', $administrationId->toString())->whereIn('relation_id', $relationIds)->where('active', true)->get()->groupBy('relation_id');
        $references = DB::table('journal_entries')->where('administration_id', $administrationId->toString())->whereIn('id', $journalIds)->pluck('reference', 'id');
        $result = [];
        foreach ($items as $item) {
            $relation = $item->relationId()->toString();
            if (! $relations->has($relation)) {
                continue;
            }
            $result[] = new BankReconciliationCandidate($item->id(), $item->relationId(), (string) $relations->get($relation), $item->type(), $item->side(), $item->controlLedgerAccountId(), $item->openAmount(), $item->openedOn()->value(), $item->dueDate(), (string) $references->get($item->journalEntryId()->toString(), ''), $ibans->get($relation, collect())->pluck('iban')->map(static fn (string $iban): string => strtoupper(str_replace(' ', '', $iban)))->all());
        }

        return $result;
    }
}
