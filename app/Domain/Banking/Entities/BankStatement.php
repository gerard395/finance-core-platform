<?php

declare(strict_types=1);

namespace App\Domain\Banking\Entities;

use App\Domain\Banking\ValueObjects\BankStatementId;
use App\Domain\Banking\ValueObjects\CanonicalizationVersion;
use App\Domain\Shared\Finance\Money;
use DateTimeImmutable;

final readonly class BankStatement
{
    /** @param list<BankStatementEntry> $entries */
    public function __construct(public BankStatementId $id, public ?string $externalId, public ?string $electronicSequence, public string $accountIdentity, public string $currency, public ?Money $openingBalance, public ?Money $closingBalance, public ?DateTimeImmutable $fromDate, public ?DateTimeImmutable $toDate, public array $entries, public int $sourceOrdinal) {}

    public function canonicalStatementHash(string $namespace, string $parserVersion, CanonicalizationVersion $version = new CanonicalizationVersion): string
    {
        $entryHashes = array_map(fn (BankStatementEntry $entry): string => $entry->canonicalEntryHash($this->accountIdentity, $namespace, $parserVersion, $version), $this->entries);
        $payload = ['namespace' => $namespace, 'parser_version' => $parserVersion, 'canonicalization_version' => $version->value, 'account' => $this->accountIdentity, 'currency' => $this->currency, 'from' => $this->fromDate?->format(DATE_ATOM), 'to' => $this->toDate?->format(DATE_ATOM), 'opening' => $this->openingBalance?->amount(), 'closing' => $this->closingBalance?->amount(), 'entries' => $entryHashes];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
