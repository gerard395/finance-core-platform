<?php

declare(strict_types=1);

namespace App\Domain\Banking\Entities;

use App\Domain\Banking\Enums\BankEntryDirection;
use App\Domain\Banking\ValueObjects\BankStatementEntryId;
use App\Domain\Banking\ValueObjects\CanonicalizationVersion;
use App\Domain\Shared\Finance\Money;
use DateTimeImmutable;

final readonly class BankStatementEntry
{
    /** @param list<string> $remittanceLines @param array<string, string|list<string>|null> $metadata */
    public function __construct(
        public BankStatementEntryId $id,
        public DateTimeImmutable $bookingDate,
        public ?DateTimeImmutable $valueDate,
        public Money $amount,
        public BankEntryDirection $direction,
        public bool $reversal,
        public ?string $accountServicerReference,
        public ?string $entryReference,
        public ?string $endToEndId,
        public ?string $counterpartyName,
        public ?string $counterpartyAccount,
        public array $remittanceLines,
        public ?string $creditorReference,
        public ?string $mandateId,
        public ?string $bankTransactionDomain,
        public ?string $bankTransactionFamily,
        public ?string $bankTransactionSubfamily,
        public ?string $bankTransactionProprietaryCode,
        public array $metadata,
        public int $sourceOrdinal,
    ) {}

    public function canonicalEntryHash(string $accountIdentity, string $namespace, string $parserVersion, CanonicalizationVersion $version = new CanonicalizationVersion): string
    {
        $payload = [
            'account' => strtoupper(str_replace(' ', '', $accountIdentity)), 'amount' => $this->amount->amount(),
            'bank_transaction_code' => [$this->bankTransactionDomain, $this->bankTransactionFamily, $this->bankTransactionSubfamily, $this->bankTransactionProprietaryCode],
            'booking_date' => $this->bookingDate->format('Y-m-d'), 'canonicalization_version' => $version->value,
            'counterparty' => [$this->counterpartyName, $this->counterpartyAccount], 'currency' => $this->amount->currency()->code(),
            'direction' => $this->direction->value, 'identifiers' => [$this->accountServicerReference, $this->entryReference, $this->endToEndId, $this->creditorReference, $this->mandateId],
            'metadata' => $this->metadata, 'namespace' => $namespace, 'parser_version' => $parserVersion,
            'remittance' => $this->remittanceLines, 'reversal' => $this->reversal, 'value_date' => $this->valueDate?->format('Y-m-d'),
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
