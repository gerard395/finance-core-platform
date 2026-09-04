<?php

declare(strict_types=1);

namespace App\Infrastructure\Banking;

use App\Application\Banking\BankImportSourceIdentityGenerator;
use App\Application\Banking\BankStatementParser;
use App\Application\Banking\BankStatementParseResult;
use App\Application\Banking\BankStatementParseStatus;
use App\Domain\Banking\Entities\BankStatement;
use App\Domain\Banking\Entities\BankStatementEntry;
use App\Domain\Banking\Enums\BankEntryDirection;
use App\Domain\Banking\ValueObjects\CamtNamespaceVersion;
use App\Domain\Banking\ValueObjects\OriginalFileHash;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Throwable;

final readonly class Camt053Parser implements BankStatementParser
{
    public const int MAX_BYTES = 5_000_000;

    public const int MAX_STATEMENTS = 100;

    public const int MAX_ENTRIES = 10_000;

    public const int MAX_TEXT = 2_000;

    public const int MAX_DEPTH = 128;

    public function __construct(private BankImportSourceIdentityGenerator $identities) {}

    public function parse(string $bytes, ?string $expectedAccountIdentity = null): BankStatementParseResult
    {
        $hash = OriginalFileHash::fromBytes($bytes);
        if (strlen($bytes) > self::MAX_BYTES) {
            return BankStatementParseResult::failure(BankStatementParseStatus::SecurityViolation, $hash);
        }
        if (preg_match('/<!DOCTYPE|<!ENTITY|<\?xml-stylesheet|<xi:include|<xinclude/i', $bytes) === 1) {
            return BankStatementParseResult::failure(BankStatementParseStatus::SecurityViolation, $hash);
        }
        if (preg_match('/>[^<]{2001,}</s', $bytes) === 1) {
            return BankStatementParseResult::failure(BankStatementParseStatus::SecurityViolation, $hash);
        }
        if ($this->exceedsDepthLimit($bytes)) {
            return BankStatementParseResult::failure(BankStatementParseStatus::SecurityViolation, $hash);
        }
        if (! mb_check_encoding($bytes, 'UTF-8')) {
            return BankStatementParseResult::failure(BankStatementParseStatus::MalformedFile, $hash);
        }

        try {
            $document = new DOMDocument;
            $previous = libxml_use_internal_errors(true);
            $loaded = $document->loadXML($bytes, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_COMPACT);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            if (! $loaded || ! $document->documentElement instanceof DOMElement) {
                return BankStatementParseResult::failure(BankStatementParseStatus::MalformedFile, $hash);
            }
            $root = $document->documentElement;
            if ($root->localName !== 'Document') {
                return BankStatementParseResult::failure(BankStatementParseStatus::UnsupportedFormat, $hash);
            }
            $namespace = CamtNamespaceVersion::tryFrom((string) $root->namespaceURI);
            if ($namespace === null) {
                return BankStatementParseResult::failure(str_contains((string) $root->namespaceURI, 'camt.053.') ? BankStatementParseStatus::UnsupportedVersion : BankStatementParseStatus::UnsupportedFormat, $hash);
            }
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('c', $namespace->value);
            $nonEuroAmounts = $xpath->query('//c:Amt[@Ccy and translate(@Ccy, "eur", "EUR") != "EUR"]');
            if ($nonEuroAmounts !== false && $nonEuroAmounts->length > 0) {
                return BankStatementParseResult::failure(BankStatementParseStatus::UnsupportedCurrency, $hash);
            }
            $statementNodes = $xpath->query('/c:Document/c:BkToCstmrStmt/c:Stmt');
            if ($statementNodes === false || $statementNodes->length === 0) {
                return BankStatementParseResult::failure(BankStatementParseStatus::MalformedFile, $hash);
            }
            if ($statementNodes->length > self::MAX_STATEMENTS) {
                return BankStatementParseResult::failure(BankStatementParseStatus::SecurityViolation, $hash);
            }
            $statements = [];
            $entryCount = 0;
            foreach ($statementNodes as $statementOrdinal => $node) {
                if (! $node instanceof DOMElement) {
                    continue;
                }
                $account = $this->normalized($this->text($xpath, 'c:Acct/c:Id/c:IBAN', $node));
                if ($account === null) {
                    return BankStatementParseResult::failure(BankStatementParseStatus::IntegrityFailure, $hash);
                }
                if ($expectedAccountIdentity !== null && $account !== $this->normalized($expectedAccountIdentity)) {
                    return BankStatementParseResult::failure(BankStatementParseStatus::BankAccountMismatch, $hash);
                }
                $currency = strtoupper($this->text($xpath, 'c:Acct/c:Ccy', $node) ?? '');
                if ($currency !== 'EUR') {
                    return BankStatementParseResult::failure(BankStatementParseStatus::UnsupportedCurrency, $hash);
                }
                foreach (['OPBD', 'CLBD'] as $balanceCode) {
                    $balances = $xpath->query("c:Bal[c:Tp/c:CdOrPrtry/c:Cd='$balanceCode']", $node);
                    if ($balances !== false && $balances->length > 1) {
                        return BankStatementParseResult::failure(BankStatementParseStatus::IntegrityFailure, $hash);
                    }
                }
                $entries = [];
                $nodes = $xpath->query('c:Ntry', $node);
                if ($nodes === false) {
                    return BankStatementParseResult::failure(BankStatementParseStatus::MalformedFile, $hash);
                }
                $entryCount += $nodes->length;
                if ($entryCount > self::MAX_ENTRIES) {
                    return BankStatementParseResult::failure(BankStatementParseStatus::SecurityViolation, $hash);
                }
                foreach ($nodes as $entryOrdinal => $entryNode) {
                    if (! $entryNode instanceof DOMElement) {
                        continue;
                    }
                    $details = $xpath->query('c:NtryDtls/c:TxDtls', $entryNode);
                    if ($details !== false && $details->length > 1) {
                        return BankStatementParseResult::failure(BankStatementParseStatus::UnsupportedEntryStructure, $hash);
                    }
                    $detail = $details !== false && $details->length === 1 ? $details->item(0) : null;
                    $entries[] = $this->entry($xpath, $entryNode, $detail, $entryOrdinal + 1);
                }
                $statements[] = new BankStatement($this->identities->statementId(), $this->text($xpath, 'c:Id', $node), $this->text($xpath, 'c:ElctrncSeqNb', $node), $account, $currency, $this->balance($xpath, $node, 'OPBD'), $this->balance($xpath, $node, 'CLBD'), $this->date($this->text($xpath, 'c:FrToDt/c:FrDtTm', $node) ?? $this->text($xpath, 'c:FrToDt/c:FrDt', $node)), $this->date($this->text($xpath, 'c:FrToDt/c:ToDtTm', $node) ?? $this->text($xpath, 'c:FrToDt/c:ToDt', $node)), $entries, $statementOrdinal + 1);
            }

            return BankStatementParseResult::success($statements, $namespace, $hash);
        } catch (Throwable) {
            return BankStatementParseResult::failure(BankStatementParseStatus::MalformedFile, $hash);
        }
    }

    private function entry(DOMXPath $xpath, DOMElement $node, ?DOMNode $detail, int $ordinal): BankStatementEntry
    {
        $currency = new Currency(strtoupper($this->attribute($xpath, 'c:Amt', 'Ccy', $node) ?? ''));
        $direction = BankEntryDirection::from($this->text($xpath, 'c:CdtDbtInd', $node) ?? '');
        $amount = $this->text($xpath, 'c:Amt', $node) ?? '';
        $signed = $direction === BankEntryDirection::Debit ? '-'.$amount : $amount;
        $context = $detail ?? $node;

        return new BankStatementEntry($this->identities->entryId(), $this->dateRequired($this->text($xpath, 'c:BookgDt/c:Dt', $node) ?? $this->text($xpath, 'c:BookgDt/c:DtTm', $node)), $this->date($this->text($xpath, 'c:ValDt/c:Dt', $node) ?? $this->text($xpath, 'c:ValDt/c:DtTm', $node)), new Money($signed, $currency), $direction, ($this->text($xpath, 'c:RvslInd', $node) ?? 'false') === 'true', $this->text($xpath, 'c:AcctSvcrRef', $node), $this->text($xpath, 'c:NtryRef', $node), $this->text($xpath, './/c:Refs/c:EndToEndId', $context), $this->text($xpath, './/c:RltdPties/c:Dbtr/c:Nm | .//c:RltdPties/c:Cdtr/c:Nm', $context), $this->normalized($this->text($xpath, './/c:RltdPties/c:DbtrAcct/c:Id/c:IBAN | .//c:RltdPties/c:CdtrAcct/c:Id/c:IBAN', $context)), $this->texts($xpath, './/c:RmtInf/c:Ustrd', $context), $this->text($xpath, './/c:RmtInf/c:Strd/c:CdtrRefInf/c:Ref', $context), $this->text($xpath, './/c:Refs/c:MndtId', $context), $this->text($xpath, 'c:BkTxCd/c:Domn/c:Cd', $node), $this->text($xpath, 'c:BkTxCd/c:Domn/c:Fmly/c:Cd', $node), $this->text($xpath, 'c:BkTxCd/c:Domn/c:Fmly/c:SubFmlyCd', $node), $this->text($xpath, 'c:BkTxCd/c:Prtry/c:Cd', $node), ['additional_information' => $this->text($xpath, './/c:AddtlTxInf', $context)], $ordinal);
    }

    private function balance(DOMXPath $xpath, DOMElement $node, string $code): ?Money
    {
        $balances = $xpath->query("c:Bal[c:Tp/c:CdOrPrtry/c:Cd='$code']", $node);
        if ($balances === false || $balances->length === 0) {
            return null;
        } if ($balances->length !== 1) {
            throw new \RuntimeException('Ambiguous balance.');
        } $bal = $balances->item(0);
        $amount = $this->text($xpath, 'c:Amt', $bal);
        $currency = $this->attribute($xpath, 'c:Amt', 'Ccy', $bal);
        $direction = $this->text($xpath, 'c:CdtDbtInd', $bal);

        return new Money($direction === 'DBIT' ? '-'.$amount : (string) $amount, new Currency((string) $currency));
    }

    private function text(DOMXPath $xpath, string $query, DOMNode $node): ?string
    {
        $value = trim((string) $xpath->evaluate('string('.$query.')', $node));
        if ($value === '') {
            return null;
        } if (mb_strlen($value) > self::MAX_TEXT) {
            throw new \RuntimeException('Text limit exceeded.');
        }

        return $value;
    }

    /** @return list<string> */
    private function texts(DOMXPath $xpath, string $query, DOMNode $node): array
    {
        $result = [];
        $nodes = $xpath->query($query, $node);
        if ($nodes !== false) {
            foreach ($nodes as $item) {
                $value = trim($item->textContent);
                if ($value !== '') {
                    $result[] = mb_substr($value, 0, self::MAX_TEXT);
                }
            }
        }

        return $result;
    }

    private function attribute(DOMXPath $xpath, string $query, string $attribute, DOMNode $node): ?string
    {
        $element = $xpath->query($query, $node)?->item(0);

        return $element instanceof DOMElement ? ($element->getAttribute($attribute) ?: null) : null;
    }

    private function normalized(?string $value): ?string
    {
        return $value === null ? null : strtoupper((string) preg_replace('/\s+/', '', $value));
    }

    private function date(?string $value): ?DateTimeImmutable
    {
        return $value === null ? null : new DateTimeImmutable($value);
    }

    private function dateRequired(?string $value): DateTimeImmutable
    {
        if ($value === null) {
            throw new \RuntimeException('Missing booking date.');
        }

        return new DateTimeImmutable($value);
    }

    private function exceedsDepthLimit(string $bytes): bool
    {
        preg_match_all('/<\/?[A-Za-z_][A-Za-z0-9_.:-]*(?:\s[^<>]*?)?\s*\/?>/', $bytes, $matches);
        $depth = 0;
        foreach ($matches[0] as $tag) {
            if (str_starts_with($tag, '</')) {
                $depth--;
            } elseif (! str_ends_with($tag, '/>')) {
                $depth++;
                if ($depth > self::MAX_DEPTH) {
                    return true;
                }
            }
        }

        return false;
    }
}
