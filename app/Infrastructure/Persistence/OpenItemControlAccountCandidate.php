<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use RuntimeException;

final readonly class OpenItemControlAccountCandidate
{
    /**
     * @param  array{id: string, administration_id: string, open_item_type: string, side: string, original_amount: string, currency: string}  $openItem
     * @param  list<array{id: string, administration_id: string, ledger_account_id: string, debit_amount: ?string, credit_amount: ?string, currency: string}>  $lines
     * @param  array<string, array{administration_id: string, type: string}>  $accounts
     */
    public function select(array $openItem, array $lines, array $accounts): string
    {
        $original = new Money($openItem['original_amount'], new Currency($openItem['currency']));
        $candidates = array_values(array_filter($lines, static function (array $line) use ($openItem, $original): bool {
            $amount = $openItem['side'] === 'debit' ? $line['debit_amount'] : $line['credit_amount'];
            $opposite = $openItem['side'] === 'debit' ? $line['credit_amount'] : $line['debit_amount'];

            return $line['administration_id'] === $openItem['administration_id']
                && $amount !== null
                && $opposite === null
                && $original->equals(new Money($amount, new Currency($line['currency'])));
        }));

        if (count($candidates) !== 1) {
            throw new RuntimeException("OpenItem {$openItem['id']} must have exactly one factual control-account candidate; found ".count($candidates).'.');
        }

        $ledgerAccountId = $candidates[0]['ledger_account_id'];
        $account = $accounts[$ledgerAccountId] ?? null;
        if ($account === null || $account['administration_id'] !== $openItem['administration_id']) {
            throw new RuntimeException("OpenItem {$openItem['id']} control-account candidate has no same-Administration LedgerAccount.");
        }

        $expectedType = $openItem['open_item_type'] === 'receivable' ? 'asset' : 'liability';
        if ($account['type'] !== $expectedType) {
            throw new RuntimeException("OpenItem {$openItem['id']} factual control account must be {$expectedType}.");
        }

        return $ledgerAccountId;
    }
}
