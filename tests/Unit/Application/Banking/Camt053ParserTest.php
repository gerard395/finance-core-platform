<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Banking;

use App\Application\Banking\BankImportSourceIdentityGenerator;
use App\Application\Banking\BankStatementParseStatus;
use App\Domain\Banking\Entities\BankStatementEntry;
use App\Domain\Banking\ValueObjects\BankImportBatchId;
use App\Domain\Banking\ValueObjects\BankStatementEntryId;
use App\Domain\Banking\ValueObjects\BankStatementId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Banking\Camt053Parser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Camt053ParserTest extends TestCase
{
    private Camt053Parser $parser;

    protected function setUp(): void
    {
        $ids = new class implements BankImportSourceIdentityGenerator
        {
            private int $next = 1;

            public function batchId(): BankImportBatchId
            {
                return new BankImportBatchId($this->uuid());
            }

            public function statementId(): BankStatementId
            {
                return new BankStatementId($this->uuid());
            }

            public function entryId(): BankStatementEntryId
            {
                return new BankStatementEntryId($this->uuid());
            }

            private function uuid(): Uuid
            {
                return new Uuid(sprintf('a1110000-0000-4000-8000-%012d', $this->next++));
            }
        };
        $this->parser = new Camt053Parser($ids);
    }

    #[DataProvider('namespaces')]
    public function test_supported_versions_normalize_equivalent_sourcefacts(string $namespace): void
    {
        $result = $this->parser->parse($this->fixture($namespace), 'NL91 ABNA 0417 1643 00');
        self::assertSame(BankStatementParseStatus::Success, $result->status);
        self::assertCount(1, $result->statements);
        $statement = $result->statements[0];
        self::assertSame('NL91ABNA0417164300', $statement->accountIdentity);
        self::assertSame('100', $statement->openingBalance?->amount());
        self::assertSame('125', $statement->closingBalance?->amount());
        self::assertCount(2, $statement->entries);
        self::assertSame('50', $statement->entries[0]->amount->amount());
        self::assertSame('-25', $statement->entries[1]->amount->amount());
        self::assertSame('ASR-1', $statement->entries[0]->accountServicerReference);
        self::assertSame('E2E-1', $statement->entries[0]->endToEndId);
        self::assertSame(['Invoice 42'], $statement->entries[0]->remittanceLines);
    }

    /** @return array<string, array{string}> */
    public static function namespaces(): array
    {
        return ['02' => ['urn:iso:std:iso:20022:tech:xsd:camt.053.001.02'], '08' => ['urn:iso:std:iso:20022:tech:xsd:camt.053.001.08']];
    }

    public function test_hashes_are_exact_and_canonical_hash_excludes_statement_and_ordinal(): void
    {
        $bytes = $this->fixture(self::namespaces()['02'][0]);
        $first = $this->parser->parse($bytes);
        $second = $this->parser->parse(str_replace('<Id>STATEMENT-1</Id>', '<Id>STATEMENT-2</Id>', $bytes));
        self::assertSame(hash('sha256', $bytes), $first->originalFileHash?->value);
        self::assertNotSame($first->originalFileHash?->value, $this->parser->parse($bytes.' ')->originalFileHash?->value);
        self::assertSame(
            $first->statements[0]->entries[0]->canonicalEntryHash('NL91ABNA0417164300', self::namespaces()['02'][0], 'camt053-00102-parser-v1'),
            $second->statements[0]->entries[0]->canonicalEntryHash('NL91ABNA0417164300', self::namespaces()['02'][0], 'camt053-00102-parser-v1'),
        );
        $entry = $first->statements[0]->entries[0];
        $differentOrdinal = new BankStatementEntry($entry->id, $entry->bookingDate, $entry->valueDate, $entry->amount, $entry->direction, $entry->reversal, $entry->accountServicerReference, $entry->entryReference, $entry->endToEndId, $entry->counterpartyName, $entry->counterpartyAccount, $entry->remittanceLines, $entry->creditorReference, $entry->mandateId, $entry->bankTransactionDomain, $entry->bankTransactionFamily, $entry->bankTransactionSubfamily, $entry->bankTransactionProprietaryCode, $entry->metadata, 99);
        self::assertSame($entry->canonicalEntryHash('NL91ABNA0417164300', self::namespaces()['02'][0], 'camt053-00102-parser-v1'), $differentOrdinal->canonicalEntryHash('NL91ABNA0417164300', self::namespaces()['02'][0], 'camt053-00102-parser-v1'));
        $changed = $this->parser->parse(str_replace('<Ustrd>Invoice 42</Ustrd>', '<Ustrd>Invoice 43</Ustrd>', $bytes));
        self::assertNotSame($entry->canonicalEntryHash('NL91ABNA0417164300', self::namespaces()['02'][0], 'camt053-00102-parser-v1'), $changed->statements[0]->entries[0]->canonicalEntryHash('NL91ABNA0417164300', self::namespaces()['02'][0], 'camt053-00102-parser-v1'));
    }

    public function test_02_and_08_have_equivalent_normalized_semantics(): void
    {
        $v02 = $this->parser->parse($this->fixture(self::namespaces()['02'][0]))->statements[0];
        $v08 = $this->parser->parse($this->fixture(self::namespaces()['08'][0]))->statements[0];
        $projection = static fn ($statement): array => [$statement->externalId, $statement->accountIdentity, $statement->currency, $statement->openingBalance?->amount(), $statement->closingBalance?->amount(), array_map(static fn ($entry): array => [$entry->bookingDate->format('Y-m-d'), $entry->valueDate?->format('Y-m-d'), $entry->amount->amount(), $entry->direction->value, $entry->accountServicerReference, $entry->endToEndId, $entry->remittanceLines], $statement->entries)];
        self::assertSame($projection($v02), $projection($v08));
    }

    public function test_typed_format_currency_account_and_structure_failures(): void
    {
        $xml = $this->fixture(self::namespaces()['02'][0]);
        self::assertSame(BankStatementParseStatus::UnsupportedVersion, $this->parser->parse(str_replace('camt.053.001.02', 'camt.053.001.04', $xml))->status);
        self::assertSame(BankStatementParseStatus::UnsupportedFormat, $this->parser->parse(str_replace('camt.053.001.02', 'pain.001.001.03', $xml))->status);
        self::assertSame(BankStatementParseStatus::UnsupportedCurrency, $this->parser->parse(str_replace('<Ccy>EUR</Ccy>', '<Ccy>USD</Ccy>', $xml))->status);
        self::assertSame(BankStatementParseStatus::BankAccountMismatch, $this->parser->parse($xml, 'NL02ABNA0123456789')->status);
        self::assertSame(BankStatementParseStatus::MalformedFile, $this->parser->parse('<broken>')->status);
        $aggregated = str_replace('</TxDtls>', '</TxDtls><TxDtls><Refs><EndToEndId>E2E-2</EndToEndId></Refs></TxDtls>', $xml);
        self::assertSame(BankStatementParseStatus::UnsupportedEntryStructure, $this->parser->parse($aggregated)->status);
    }

    public function test_multiple_statements_are_atomic_for_account_match(): void
    {
        $xml = $this->fixture(self::namespaces()['08'][0]);
        $statement = $this->between($xml, '<Stmt>', '</Stmt>');
        $multiple = str_replace('</BkToCstmrStmt>', '<Stmt>'.$statement.'</Stmt></BkToCstmrStmt>', $xml);
        self::assertCount(2, $this->parser->parse($multiple, 'NL91ABNA0417164300')->statements);
        $mismatch = str_replace('NL91ABNA0417164300', 'NL02ABNA0123456789', $statement);
        $multiple = str_replace('</BkToCstmrStmt>', '<Stmt>'.$mismatch.'</Stmt></BkToCstmrStmt>', $xml);
        self::assertSame(BankStatementParseStatus::BankAccountMismatch, $this->parser->parse($multiple, 'NL91ABNA0417164300')->status);
    }

    public function test_xml_security_payloads_and_limits_fail_safely(): void
    {
        $namespace = self::namespaces()['02'][0];
        $xxe = '<?xml version="1.0"?><!DOCTYPE x [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><Document xmlns="'.$namespace.'"><BkToCstmrStmt>&xxe;</BkToCstmrStmt></Document>';
        $laughs = '<?xml version="1.0"?><!DOCTYPE lolz [<!ENTITY lol "lol"><!ENTITY lol2 "&lol;&lol;">]><Document xmlns="'.$namespace.'">&lol2;</Document>';
        $xinclude = '<Document xmlns="'.$namespace.'" xmlns:xi="http://www.w3.org/2001/XInclude"><xi:include href="file:///etc/passwd"/></Document>';
        self::assertSame(BankStatementParseStatus::SecurityViolation, $this->parser->parse($xxe)->status);
        self::assertSame(BankStatementParseStatus::SecurityViolation, $this->parser->parse($laughs)->status);
        self::assertSame(BankStatementParseStatus::SecurityViolation, $this->parser->parse($xinclude)->status);
        self::assertSame(BankStatementParseStatus::SecurityViolation, $this->parser->parse(str_repeat('x', Camt053Parser::MAX_BYTES + 1))->status);
        self::assertSame(BankStatementParseStatus::SecurityViolation, $this->parser->parse(str_replace('<Id>STATEMENT-1</Id>', '<Id>'.str_repeat('x', Camt053Parser::MAX_TEXT + 1).'</Id>', $this->fixture($namespace)))->status);
        $deep = '<?xml version="1.0"?><Document xmlns="'.$namespace.'">'.str_repeat('<x>', Camt053Parser::MAX_DEPTH).str_repeat('</x>', Camt053Parser::MAX_DEPTH).'</Document>';
        self::assertSame(BankStatementParseStatus::SecurityViolation, $this->parser->parse($deep)->status);
        self::assertSame(BankStatementParseStatus::MalformedFile, $this->parser->parse("\xC3\x28")->status);
    }

    public function test_excessive_entry_count_and_ambiguous_balances_are_typed_failures(): void
    {
        $namespace = self::namespaces()['02'][0];
        $entry = '<Ntry><Amt Ccy="EUR">1</Amt><CdtDbtInd>CRDT</CdtDbtInd><BookgDt><Dt>2026-09-01</Dt></BookgDt></Ntry>';
        $minimal = '<?xml version="1.0"?><Document xmlns="'.$namespace.'"><BkToCstmrStmt><Stmt><Acct><Id><IBAN>NL91ABNA0417164300</IBAN></Id><Ccy>EUR</Ccy></Acct>'.str_repeat($entry, Camt053Parser::MAX_ENTRIES + 1).'</Stmt></BkToCstmrStmt></Document>';
        self::assertSame(BankStatementParseStatus::SecurityViolation, $this->parser->parse($minimal)->status);
        $statement = $this->between($this->fixture($namespace), '<Stmt>', '</Stmt>');
        $excessiveStatements = '<?xml version="1.0"?><Document xmlns="'.$namespace.'"><BkToCstmrStmt>'.str_repeat('<Stmt>'.$statement.'</Stmt>', Camt053Parser::MAX_STATEMENTS + 1).'</BkToCstmrStmt></Document>';
        self::assertSame(BankStatementParseStatus::SecurityViolation, $this->parser->parse($excessiveStatements)->status);
        $fixture = $this->fixture($namespace);
        $opening = $this->between($fixture, '<Bal>', '</Bal>');
        $ambiguous = str_replace('</Bal><Bal>', '</Bal><Bal>'.$opening.'</Bal><Bal>', $fixture);
        self::assertSame(BankStatementParseStatus::IntegrityFailure, $this->parser->parse($ambiguous)->status);
    }

    private function fixture(string $namespace): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Document xmlns="{$namespace}"><BkToCstmrStmt><Stmt><Id>STATEMENT-1</Id><ElctrncSeqNb>7</ElctrncSeqNb><FrToDt><FrDtTm>2026-09-01T00:00:00+00:00</FrDtTm><ToDtTm>2026-09-02T23:59:59+00:00</ToDtTm></FrToDt><Acct><Id><IBAN>NL91ABNA0417164300</IBAN></Id><Ccy>EUR</Ccy></Acct>
<Bal><Tp><CdOrPrtry><Cd>OPBD</Cd></CdOrPrtry></Tp><Amt Ccy="EUR">100.00</Amt><CdtDbtInd>CRDT</CdtDbtInd></Bal><Bal><Tp><CdOrPrtry><Cd>CLBD</Cd></CdOrPrtry></Tp><Amt Ccy="EUR">125.00</Amt><CdtDbtInd>CRDT</CdtDbtInd></Bal>
<Ntry><Amt Ccy="EUR">50.00</Amt><CdtDbtInd>CRDT</CdtDbtInd><BookgDt><Dt>2026-09-01</Dt></BookgDt><ValDt><Dt>2026-09-01</Dt></ValDt><AcctSvcrRef>ASR-1</AcctSvcrRef><NtryRef>ENTRY-1</NtryRef><BkTxCd><Domn><Cd>PMNT</Cd><Fmly><Cd>RCDT</Cd><SubFmlyCd>ESCT</SubFmlyCd></Fmly></Domn></BkTxCd><NtryDtls><TxDtls><Refs><EndToEndId>E2E-1</EndToEndId></Refs><RltdPties><Dbtr><Nm>Example Customer</Nm></Dbtr><DbtrAcct><Id><IBAN>DE89370400440532013000</IBAN></Id></DbtrAcct></RltdPties><RmtInf><Ustrd>Invoice 42</Ustrd></RmtInf></TxDtls></NtryDtls></Ntry>
<Ntry><Amt Ccy="EUR">25.00</Amt><CdtDbtInd>DBIT</CdtDbtInd><BookgDt><Dt>2026-09-02</Dt></BookgDt><NtryRef>ENTRY-2</NtryRef><NtryDtls><TxDtls><Refs><EndToEndId>E2E-2</EndToEndId></Refs><RltdPties><Cdtr><Nm>Example Supplier</Nm></Cdtr></RltdPties><RmtInf><Strd><CdtrRefInf><Ref>RF18539007547034</Ref></CdtrRefInf></Strd></RmtInf></TxDtls></NtryDtls></Ntry>
</Stmt></BkToCstmrStmt></Document>
XML;
    }

    private function between(string $value, string $start, string $end): string
    {
        $offset = strpos($value, $start) + strlen($start);

        return substr($value, $offset, strpos($value, $end, $offset) - $offset);
    }
}
