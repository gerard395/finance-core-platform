<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Banking;

use App\Application\Banking\BankImportArtifactStorage;
use App\Application\Banking\BankImportSourceRepository;
use App\Application\Banking\BankStatementParser;
use App\Application\Banking\ConfirmBankImport;
use App\Application\Banking\ConfirmBankImportStatus;
use App\Application\Banking\StoreBankImportArtifact;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\BankImportBatch;
use App\Domain\Banking\ValueObjects\AdministrationBankAccountId;
use App\Domain\Banking\ValueObjects\BankImportBatchId;
use App\Domain\Banking\ValueObjects\CanonicalizationVersion;
use App\Domain\Banking\ValueObjects\SourceFormat;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Throwable;

final class BankImportSourcePersistenceTest extends TestCase
{
    use RefreshDatabase;

    private const string A = 'a1000000-0000-4000-8000-000000000001';

    private const string B = 'b1000000-0000-4000-8000-000000000001';

    private const string USER = 'a2000000-0000-4000-8000-000000000001';

    private const string BANK_A = 'a3000000-0000-4000-8000-000000000001';

    private const string BANK_B = 'b3000000-0000-4000-8000-000000000001';

    private const string BATCH = 'a4000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        self::assertSame('testing', app()->environment());
        self::assertSame('testing', config('database.connections.mysql.database'));
        self::assertSame('testing', DB::selectOne('select database() as name')->name);
        foreach ([self::A => 'A', self::B => 'B'] as $id => $suffix) {
            DB::table('administrations')->insert(['id' => $id, 'code' => 'BIR-'.$suffix, 'name' => 'BIR '.$suffix, 'base_currency' => 'EUR', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('domain_users')->insert(['id' => self::USER, 'display_name' => 'Importer', 'email' => 'importer@example.test', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('administration_bank_accounts')->insert([
            ['id' => self::BANK_A, 'administration_id' => self::A, 'iban' => 'NL91ABNA0417164300', 'bic' => null, 'account_holder' => 'Account A', 'label' => 'Bank A', 'currency' => 'EUR', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => self::BANK_B, 'administration_id' => self::B, 'iban' => 'NL91ABNA0417164300', 'bic' => null, 'account_holder' => 'Account B', 'label' => 'Bank B', 'currency' => 'EUR', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_insert_only_roundtrip_is_tenant_scoped_and_cross_tenant_association_fails(): void
    {
        $repository = $this->app->make(BankImportSourceRepository::class);
        $batch = $this->batch(self::A, self::BANK_A, self::BATCH);
        self::assertTrue($repository->insert($batch));
        $read = $repository->find($this->admin(self::A), $batch->id);
        self::assertNotNull($read);
        self::assertSame('STATEMENT-1', $read->statements[0]->externalId);
        self::assertSame('50', $read->statements[0]->entries[0]->amount->amount());
        self::assertSame(1, $read->statements[0]->sourceOrdinal);
        self::assertSame(1, $read->statements[0]->entries[0]->sourceOrdinal);
        self::assertSame('ASR-1', $read->statements[0]->entries[0]->accountServicerReference);
        self::assertNull($read->statements[0]->entries[0]->valueDate);
        self::assertNull($read->statements[0]->entries[0]->counterpartyName);
        self::assertSame($batch->originalFileHash->value, $read->originalFileHash->value);
        self::assertSame($batch->parserVersion, $read->parserVersion);
        self::assertSame($batch->canonicalizationVersion->value, $read->canonicalizationVersion->value);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', DB::table('bank_statements')->value('canonical_statement_hash'));
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', DB::table('bank_statement_entries')->value('canonical_entry_hash'));
        self::assertNull($repository->find($this->admin(self::B), $batch->id));
        self::assertCount(1, $repository->list($this->admin(self::A)));
        self::assertCount(0, $repository->list($this->admin(self::B)));
        self::assertFalse($repository->insert($this->batch(self::A, self::BANK_B, 'a4000000-0000-4000-8000-000000000002')));
        self::assertDatabaseCount('bank_import_batches', 1);
        self::assertDatabaseCount('bank_statements', 1);
        self::assertDatabaseCount('bank_statement_entries', 1);
        try {
            DB::table('bank_import_batches')->where('id', self::BATCH)->delete();
            self::fail('RESTRICT must prevent deletion of a batch with statements.');
        } catch (QueryException) {
            self::assertDatabaseCount('bank_import_batches', 1);
        }
        try {
            DB::table('bank_statements')->delete();
            self::fail('RESTRICT must prevent deletion of a statement with entries.');
        } catch (QueryException) {
            self::assertDatabaseCount('bank_statements', 1);
        }
    }

    public function test_confirm_import_validates_balance_promotes_storage_and_creates_no_financial_facts(): void
    {
        Storage::fake('bank_imports');
        $before = $this->financialCounts();
        $result = $this->confirm($this->balancedXml('STATEMENT-C', 'ASR-C'));
        self::assertSame(ConfirmBankImportStatus::Success, $result);
        self::assertDatabaseCount('bank_import_batches', 1);
        self::assertDatabaseCount('bank_statements', 1);
        self::assertDatabaseCount('bank_statement_entries', 2);
        self::assertSame('account_servicer_reference', DB::table('bank_statement_entries')->where('account_servicer_reference', 'ASR-C')->value('deduplication_kind'));
        self::assertSame($before, $this->financialCounts());
        self::assertCount(1, Storage::disk('bank_imports')->allFiles('retained'));
        self::assertCount(0, Storage::disk('bank_imports')->allFiles('quarantine'));
    }

    public function test_confirm_supports_02_and_atomic_multiple_statements(): void
    {
        Storage::fake('bank_imports');
        $v02 = str_replace('camt.053.001.08', 'camt.053.001.02', $this->balancedXml('V02', 'V02-ASR'));
        self::assertSame(ConfirmBankImportStatus::Success, $this->confirm($v02));
        DB::table('bank_statement_entries')->delete();
        DB::table('bank_statements')->delete();
        DB::table('bank_import_batches')->delete();
        $xml = $this->balancedXml('MULTI-A', 'MULTI-ASR-A');
        $statement = $this->between($xml, '<Stmt>', '</Stmt>');
        $second = str_replace(['MULTI-A', 'MULTI-ASR-A'], ['MULTI-B', 'MULTI-ASR-B'], $statement);
        $multiple = str_replace('</BkToCstmrStmt>', '<Stmt>'.$second.'</Stmt></BkToCstmrStmt>', $xml);
        self::assertSame(ConfirmBankImportStatus::Success, $this->confirm($multiple));
        self::assertDatabaseCount('bank_statements', 2);
        self::assertDatabaseCount('bank_statement_entries', 4);
    }

    public function test_confirm_import_returns_typed_balance_account_and_retry_outcomes_atomically(): void
    {
        Storage::fake('bank_imports');
        self::assertSame(ConfirmBankImportStatus::StatementBalanceMismatch, $this->confirm(str_replace('<Amt Ccy="EUR">125</Amt><CdtDbtInd>CRDT</CdtDbtInd></Bal>', '<Amt Ccy="EUR">126</Amt><CdtDbtInd>CRDT</CdtDbtInd></Bal>', $this->balancedXml('BAD-BAL', 'ASR-B'))));
        self::assertSame(ConfirmBankImportStatus::MissingStatementBalance, $this->confirm(preg_replace('/<Bal><Tp><CdOrPrtry><Cd>OPBD<\/Cd><\/CdOrPrtry><\/Tp>.*?<\/Bal>/', '', $this->balancedXml('MISSING', 'ASR-M'))));
        self::assertSame(ConfirmBankImportStatus::BankAccountMismatch, $this->confirm(str_replace('NL91ABNA0417164300', 'NL02ABNA0123456789', $this->balancedXml('WRONG', 'ASR-W'))));
        self::assertDatabaseCount('bank_import_batches', 0);
        $xml = $this->balancedXml('DUP-BATCH', 'ASR-D');
        self::assertSame(ConfirmBankImportStatus::Success, $this->confirm($xml));
        self::assertSame(ConfirmBankImportStatus::DuplicateBatch, $this->confirm($xml));
        self::assertSame(ConfirmBankImportStatus::DuplicateStatement, $this->confirm(str_replace('</Document>', "\n</Document>", $xml)));
        self::assertDatabaseCount('bank_import_batches', 1);
    }

    public function test_entry_identity_priority_and_overlapping_statement_dedupe(): void
    {
        Storage::fake('bank_imports');
        self::assertSame(ConfirmBankImportStatus::Success, $this->confirm($this->balancedXml('S-ASR', 'ASR-X', 'ENTRY-X')));
        self::assertSame(ConfirmBankImportStatus::DuplicateEntry, $this->confirm($this->balancedXml('S-OTHER', 'ASR-X', 'OTHER')));
        self::assertSame('account_servicer_reference', DB::table('bank_statement_entries')->where('account_servicer_reference', 'ASR-X')->value('deduplication_kind'));
        self::assertSame(ConfirmBankImportStatus::Success, $this->confirm($this->balancedXml('S-ENTRY', null, 'ENTRY-Y', 'Other remittance')));
        self::assertSame('entry_reference', DB::table('bank_statement_entries')->where('entry_reference', 'ENTRY-Y')->value('deduplication_kind'));
        self::assertSame(ConfirmBankImportStatus::Success, $this->confirm($this->balancedXml('S-HASH', null, null, 'Unique fallback')));
        self::assertSame('canonical_hash', DB::table('bank_statement_entries')->whereNull('entry_reference')->where('source_ordinal', 1)->orderByDesc('id')->value('deduplication_kind'));
    }

    public function test_same_source_identities_are_independent_per_tenant(): void
    {
        Storage::fake('bank_imports');
        $xml = $this->balancedXml('TENANT-SAME', 'TENANT-ASR');
        self::assertSame(ConfirmBankImportStatus::Success, $this->confirm($xml, self::A, self::BANK_A));
        self::assertSame(ConfirmBankImportStatus::Success, $this->confirm($xml, self::B, self::BANK_B));
        self::assertDatabaseCount('bank_import_batches', 2);
    }

    public function test_storage_and_staged_persistence_failures_leave_no_source_or_retained_orphans(): void
    {
        Storage::fake('bank_imports');
        $bytes = $this->balancedXml('FAILURE', 'FAILURE-ASR');
        $artifact = $this->app->make(StoreBankImportArtifact::class)->execute($bytes)->artifact;
        self::assertNotNull($artifact);
        $realStorage = $this->app->make(BankImportArtifactStorage::class);
        $this->app->instance(BankImportArtifactStorage::class, new class($realStorage) implements BankImportArtifactStorage
        {
            public function __construct(private BankImportArtifactStorage $inner) {}

            public function storeImmutable(string $storageKey, string $bytes): bool
            {
                return $this->inner->storeImmutable($storageKey, $bytes);
            }

            public function read(string $storageKey): ?string
            {
                return $this->inner->read($storageKey);
            }

            public function exists(string $storageKey): bool
            {
                return $this->inner->exists($storageKey);
            }

            public function promoteToRetained(string $temporaryKey, string $retainedKey, string $expectedSha256): bool
            {
                return false;
            }

            public function restoreToQuarantine(string $retainedKey, string $temporaryKey, string $expectedSha256): bool
            {
                return $this->inner->restoreToQuarantine($retainedKey, $temporaryKey, $expectedSha256);
            }

            public function deleteTemporary(string $storageKey): void
            {
                $this->inner->deleteTemporary($storageKey);
            }
        });
        $parsed = $this->app->make(BankStatementParser::class)->parse($bytes);
        $result = $this->app->make(ConfirmBankImport::class)->execute($this->admin(self::A), new AdministrationBankAccountId(new Uuid(self::BANK_A)), $parsed, $artifact, new UserId(new Uuid(self::USER)));
        self::assertSame(ConfirmBankImportStatus::StorageFailure, $result->status);
        self::assertDatabaseCount('bank_import_batches', 0);
        self::assertCount(0, Storage::disk('bank_imports')->allFiles('retained'));

        $this->app->instance(BankImportArtifactStorage::class, $realStorage);
        $realRepository = $this->app->make(BankImportSourceRepository::class);
        $this->app->instance(BankImportSourceRepository::class, new class($realRepository) implements BankImportSourceRepository
        {
            public function __construct(private BankImportSourceRepository $inner) {}

            public function list(AdministrationId $administrationId): array
            {
                return $this->inner->list($administrationId);
            }

            public function find(AdministrationId $administrationId, BankImportBatchId $id): ?BankImportBatch
            {
                return $this->inner->find($administrationId, $id);
            }

            public function conflict(BankImportBatch $batch): ?ConfirmBankImportStatus
            {
                return $this->inner->conflict($batch);
            }

            public function insert(BankImportBatch $batch): bool
            {
                $this->inner->insert($batch);

                return false;
            }
        });
        $result = $this->app->make(ConfirmBankImport::class)->execute($this->admin(self::A), new AdministrationBankAccountId(new Uuid(self::BANK_A)), $parsed, $artifact, new UserId(new Uuid(self::USER)));
        self::assertSame(ConfirmBankImportStatus::IntegrityFailure, $result->status);
        self::assertDatabaseCount('bank_import_batches', 0);
        self::assertDatabaseCount('bank_statements', 0);
        self::assertDatabaseCount('bank_statement_entries', 0);
        self::assertCount(0, Storage::disk('bank_imports')->allFiles('retained'));
        self::assertTrue($realStorage->exists($artifact->storageKey));
    }

    public function test_real_mysql_races_are_serialized_with_typed_duplicate_outcomes(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required.');
        }
        Storage::fake('bank_imports');
        DB::commit();
        $same = $this->balancedXml('RACE-BATCH', 'RACE-ASR');
        $this->assertRace([$same, $same], ConfirmBankImportStatus::DuplicateBatch, 1, 2);
        $base = $this->balancedXml('RACE-STATEMENT', 'RACE-STATEMENT-ASR');
        $this->assertRace([$base, str_replace('</Document>', "\n</Document>", $base)], ConfirmBankImportStatus::DuplicateStatement, 1, 2);
        $this->assertRace([$this->balancedXml('RACE-ENTRY-A', 'RACE-SHARED'), $this->balancedXml('RACE-ENTRY-B', 'RACE-SHARED')], ConfirmBankImportStatus::DuplicateEntry, 1, 2);
    }

    /** @param array{string, string} $documents */
    private function assertRace(array $documents, ConfirmBankImportStatus $loser, int $batches, int $entries): void
    {
        $files = [tempnam(sys_get_temp_dir(), 'bir-race-a-'), tempnam(sys_get_temp_dir(), 'bir-race-b-')];
        $children = [];
        foreach ($files as $index => $file) {
            self::assertIsString($file);
            $pid = pcntl_fork();
            self::assertNotSame(-1, $pid);
            if ($pid === 0) {
                try {
                    DB::purge();
                    file_put_contents($file, $this->confirm($documents[$index])->value);
                    exit(0);
                } catch (Throwable $failure) {
                    file_put_contents($file, 'error:'.$failure::class);
                    exit(1);
                }
            }
            $children[] = $pid;
        }
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            self::assertTrue(pcntl_wifexited($status));
            self::assertSame(0, pcntl_wexitstatus($status));
        }
        $outcomes = array_map(static fn ($file): string => trim((string) file_get_contents($file)), $files);
        sort($outcomes);
        $expected = [ConfirmBankImportStatus::Success->value, $loser->value];
        sort($expected);
        self::assertSame($expected, $outcomes);
        DB::purge();
        self::assertSame($batches, DB::table('bank_import_batches')->count());
        self::assertSame($entries, DB::table('bank_statement_entries')->count());
        DB::table('bank_statement_entries')->delete();
        DB::table('bank_statements')->delete();
        DB::table('bank_import_batches')->delete();
        foreach ($files as $file) {
            unlink($file);
        }
    }

    private function confirm(string $bytes, string $administration = self::A, string $bank = self::BANK_A): ConfirmBankImportStatus
    {
        $artifact = $this->app->make(StoreBankImportArtifact::class)->execute($bytes)->artifact;
        self::assertNotNull($artifact);
        $parsed = $this->app->make(BankStatementParser::class)->parse($bytes);

        return $this->app->make(ConfirmBankImport::class)->execute($this->admin($administration), new AdministrationBankAccountId(new Uuid($bank)), $parsed, $artifact, new UserId(new Uuid(self::USER)))->status;
    }

    private function balancedXml(string $statementId, ?string $servicerReference, ?string $entryReference = null, string $remittance = 'Payment'): string
    {
        $asr = $servicerReference === null ? '' : '<AcctSvcrRef>'.$servicerReference.'</AcctSvcrRef>';
        $reference = $entryReference === null ? '' : '<NtryRef>'.$entryReference.'</NtryRef>';

        return '<?xml version="1.0"?><Document xmlns="urn:iso:std:iso:20022:tech:xsd:camt.053.001.08"><BkToCstmrStmt><Stmt><Id>'.$statementId.'</Id><Acct><Id><IBAN>NL91ABNA0417164300</IBAN></Id><Ccy>EUR</Ccy></Acct><Bal><Tp><CdOrPrtry><Cd>OPBD</Cd></CdOrPrtry></Tp><Amt Ccy="EUR">100</Amt><CdtDbtInd>CRDT</CdtDbtInd></Bal><Bal><Tp><CdOrPrtry><Cd>CLBD</Cd></CdOrPrtry></Tp><Amt Ccy="EUR">125</Amt><CdtDbtInd>CRDT</CdtDbtInd></Bal><Ntry><Amt Ccy="EUR">50</Amt><CdtDbtInd>CRDT</CdtDbtInd><BookgDt><Dt>2026-09-03</Dt></BookgDt>'.$asr.$reference.'<NtryDtls><TxDtls><RmtInf><Ustrd>'.$remittance.'</Ustrd></RmtInf></TxDtls></NtryDtls></Ntry><Ntry><Amt Ccy="EUR">25</Amt><CdtDbtInd>DBIT</CdtDbtInd><BookgDt><Dt>2026-09-03</Dt></BookgDt><AcctSvcrRef>'.$statementId.'-SECOND</AcctSvcrRef></Ntry></Stmt></BkToCstmrStmt></Document>';
    }

    private function between(string $value, string $start, string $end): string
    {
        $offset = strpos($value, $start) + strlen($start);

        return substr($value, $offset, strpos($value, $end, $offset) - $offset);
    }

    /** @return array<string, int> */
    private function financialCounts(): array
    {
        return ['bank_transactions' => DB::table('bank_transactions')->count(), 'payments' => DB::table('payments')->count(), 'payment_allocations' => DB::table('payment_allocations')->count(), 'journal_entries' => DB::table('journal_entries')->count(), 'open_items' => DB::table('open_items')->count(), 'settlements' => DB::table('open_item_settlements')->count(), 'matches' => DB::table('open_item_matches')->count(), 'reversals' => DB::table('bank_transaction_reversals')->count()];
    }

    private function batch(string $administration, string $bank, string $id): BankImportBatch
    {
        $bytes = '<?xml version="1.0"?><Document xmlns="urn:iso:std:iso:20022:tech:xsd:camt.053.001.08"><BkToCstmrStmt><Stmt><Id>STATEMENT-1</Id><Acct><Id><IBAN>NL91ABNA0417164300</IBAN></Id><Ccy>EUR</Ccy></Acct><Ntry><Amt Ccy="EUR">50.00</Amt><CdtDbtInd>CRDT</CdtDbtInd><BookgDt><Dt>2026-09-03</Dt></BookgDt><AcctSvcrRef>ASR-1</AcctSvcrRef></Ntry></Stmt></BkToCstmrStmt></Document>';
        $parsed = $this->app->make(BankStatementParser::class)->parse($bytes);

        return new BankImportBatch(new BankImportBatchId(new Uuid($id)), $this->admin($administration), new AdministrationBankAccountId(new Uuid($bank)), SourceFormat::Camt053, $parsed->namespace, $parsed->originalFileHash, $parsed->namespace->parserVersion(), new CanonicalizationVersion, new UserId(new Uuid(self::USER)), new DateTimeImmutable('2026-09-03T10:00:00+00:00'), 'retained/random.xml', $parsed->statements);
    }

    private function admin(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }
}
