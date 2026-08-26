<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use RuntimeException;

final readonly class OpenItemControlAccountBackfill
{
    public function __construct(private ConnectionInterface $connection) {}

    /** @return array<string, string> OpenItemId => LedgerAccountId */
    public function mappings(): array
    {
        $mappings = [];
        $openItems = $this->connection->table('open_items')->orderBy('id')->get();

        foreach ($openItems as $openItem) {
            $entryExists = $this->connection->table('journal_entries')
                ->where('id', $openItem->journal_entry_id)
                ->where('administration_id', $openItem->administration_id)
                ->exists();

            if (! $entryExists) {
                throw new RuntimeException("OpenItem {$openItem->id} has no same-Administration source JournalEntry.");
            }

            $lines = $this->connection->table('journal_entry_lines')
                ->where('administration_id', $openItem->administration_id)
                ->where('journal_entry_id', $openItem->journal_entry_id)
                ->orderBy('id')
                ->get()
                ->map(static fn (object $line): array => [
                    'id' => (string) $line->id,
                    'administration_id' => (string) $line->administration_id,
                    'ledger_account_id' => (string) $line->ledger_account_id,
                    'debit_amount' => $line->debit_amount === null ? null : (string) $line->debit_amount,
                    'credit_amount' => $line->credit_amount === null ? null : (string) $line->credit_amount,
                    'currency' => (string) $line->currency,
                ])->all();
            $accounts = $this->connection->table('ledger_accounts')
                ->where('administration_id', $openItem->administration_id)
                ->get(['id', 'administration_id', 'type'])
                ->mapWithKeys(static fn (object $account): array => [(string) $account->id => [
                    'administration_id' => (string) $account->administration_id,
                    'type' => (string) $account->type,
                ]])->all();

            $mappings[(string) $openItem->id] = (new OpenItemControlAccountCandidate)->select([
                'id' => (string) $openItem->id,
                'administration_id' => (string) $openItem->administration_id,
                'open_item_type' => (string) $openItem->open_item_type,
                'side' => (string) $openItem->side,
                'original_amount' => (string) $openItem->original_amount,
                'currency' => (string) $openItem->currency,
            ], $lines, $accounts);
        }

        return $mappings;
    }
}
