<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Banking;

use App\Application\Banking\BankImportSourceRepository;
use App\Application\Banking\BankStatementParser;
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
use Tests\TestCase;

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
            ['id' => self::BANK_A, 'administration_id' => self::A, 'iban' => 'NL91ABNA0417164300', 'bic' => null, 'account_holder' => 'A', 'label' => 'A', 'currency' => 'EUR', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => self::BANK_B, 'administration_id' => self::B, 'iban' => 'NL02ABNA0123456789', 'bic' => null, 'account_holder' => 'B', 'label' => 'B', 'currency' => 'EUR', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
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
