<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence;

use App\Application\Sales\DocumentArtifactRepository;
use App\Application\Sales\SalesDocumentSource;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Entities\DocumentArtifact;
use App\Domain\Sales\Enums\SalesDocumentType;
use App\Domain\Sales\ValueObjects\ArtifactId;
use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Large;
use Tests\TestCase;
use Throwable;

#[Large]
final class EloquentDocumentArtifactRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private const A = 'a1000000-0000-4000-8000-000000000001';

    private const B = 'b1000000-0000-4000-8000-000000000001';

    private const Q = 'a2000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([self::A => 'A', self::B => 'B'] as $id => $suffix) {
            DB::table('administrations')->insert(['id' => $id, 'code' => 'ART-'.$suffix, 'name' => 'Artifact '.$suffix, 'base_currency' => 'EUR', 'status' => 'active', 'organisation_display_name' => 'Artifact '.$suffix, 'organisation_legal_name' => 'Artifact '.$suffix.' B.V.', 'organisation_chamber_of_commerce_number' => '12345678', 'document_address_line_1' => 'Teststraat 1', 'document_postal_code' => '1234AB', 'document_city' => 'Amsterdam', 'document_country_code' => 'NL', 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('relations')->insert(['id' => 'a3000000-0000-4000-8000-000000000001', 'administration_id' => self::A, 'code' => 'REL-A', 'display_name' => 'Customer', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('customers')->insert(['id' => 'a4000000-0000-4000-8000-000000000001', 'administration_id' => self::A, 'relation_id' => 'a3000000-0000-4000-8000-000000000001', 'customer_number' => 'C000001', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('quotations')->insert(['id' => self::Q, 'administration_id' => self::A, 'quotation_number' => 'Q000001', 'customer_id' => 'a4000000-0000-4000-8000-000000000001', 'customer_relation_id_snapshot' => 'a3000000-0000-4000-8000-000000000001', 'customer_number_snapshot' => 'C000001', 'customer_name_snapshot' => 'Customer', 'quotation_address_id_snapshot' => 'a6000000-0000-4000-8000-000000000001', 'quotation_address_type_snapshot' => 'quotation', 'quotation_address_line_1_snapshot' => 'Klantstraat 1', 'quotation_postal_code_snapshot' => '1000AA', 'quotation_city_snapshot' => 'Utrecht', 'quotation_country_code_snapshot' => 'NL', 'currency' => 'EUR', 'status' => 'draft', 'quotation_date' => '2026-08-25', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('quotation_lines')->insert(['id' => 'a7000000-0000-4000-8000-000000000001', 'administration_id' => self::A, 'quotation_id' => self::Q, 'description' => 'Veilige dienstverlening', 'quantity' => '2', 'unit_price_amount' => '50', 'currency' => 'EUR', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_metadata_link_roundtrip_reuse_version_and_tenant_isolation(): void
    {
        $repo = $this->app->make(DocumentArtifactRepository::class);
        $source = SalesDocumentSource::quotation(new QuotationId(new Uuid(self::Q)));
        $artifact = $this->artifact(self::A, 'a5000000-0000-4000-8000-000000000001', hash('sha256', 'render-a'));
        self::assertSame(1, $repo->nextVersion($this->admin(self::A), $source));
        self::assertTrue($artifact->id->equals($repo->persist($artifact)->id));
        self::assertSame($artifact->sha256, $repo->findBySourceFingerprint($this->admin(self::A), $source, $artifact->renderFingerprint)?->sha256);
        self::assertNull($repo->find($this->admin(self::B), $artifact->id));
        self::assertSame(2, $repo->nextVersion($this->admin(self::A), $source));
        self::assertDatabaseCount('document_artifacts', 1);
        self::assertDatabaseCount('quotation_document_artifacts', 1);
    }

    public function test_database_rejects_cross_tenant_source_link(): void
    {
        $artifact = $this->artifact(self::B, 'b5000000-0000-4000-8000-000000000001', hash('sha256', 'render-b'));
        DB::table('document_artifacts')->insert(['id' => $artifact->id->toString(), 'administration_id' => self::B, 'document_type' => 'quotation', 'version' => 1, 'mime_type' => 'application/pdf', 'filename' => 'Offerte.pdf', 'storage_key' => $artifact->storageKey, 'sha256' => $artifact->sha256, 'byte_size' => 4, 'generated_at' => now(), 'template_version' => 'quotation-v1', 'renderer_version' => 'test', 'render_fingerprint' => $artifact->renderFingerprint, 'locale' => 'nl', 'created_at' => now(), 'updated_at' => now()]);
        $this->expectException(QueryException::class);
        DB::table('quotation_document_artifacts')->insert(['artifact_id' => $artifact->id->toString(), 'administration_id' => self::B, 'quotation_id' => self::Q, 'render_fingerprint' => $artifact->renderFingerprint, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_real_mysql_concurrency_keeps_exactly_one_source_fingerprint_artifact(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required for the artifact concurrency test.');
        }
        DB::commit();
        $fingerprint = hash('sha256', 'same-semantic-render-input');
        $files = [tempnam(sys_get_temp_dir(), 'artifact-a-'), tempnam(sys_get_temp_dir(), 'artifact-b-')];
        $children = [];
        foreach ($files as $index => $file) {
            self::assertIsString($file);
            $pid = pcntl_fork();
            self::assertNotSame(-1, $pid);
            if ($pid === 0) {
                try {
                    DB::purge();
                    $artifact = $this->artifact(self::A, sprintf('a5000000-0000-4000-8000-%012d', $index + 10), $fingerprint);
                    $saved = $this->app->make(DocumentArtifactRepository::class)->persist($artifact);
                    file_put_contents($file, $saved->id->toString());
                    exit(0);
                } catch (Throwable $exception) {
                    file_put_contents($file, 'ERROR:'.$exception->getMessage());
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
        $ids = array_map(static fn (string $file): string => trim((string) file_get_contents($file)), $files);
        self::assertSame($ids[0], $ids[1]);
        self::assertSame(1, DB::table('document_artifacts')->count());
        self::assertSame(1, DB::table('quotation_document_artifacts')->count());
        foreach ($files as $file) {
            unlink($file);
        }
        DB::table('quotation_document_artifacts')->delete();
        DB::table('document_artifacts')->delete();
        DB::table('quotation_lines')->delete();
        DB::table('quotations')->delete();
        DB::table('customers')->delete();
        DB::table('relations')->delete();
        DB::table('administrations')->delete();
        DB::beginTransaction();
    }

    private function artifact(string $admin, string $id, string $fingerprint): DocumentArtifact
    {
        return new DocumentArtifact(new ArtifactId(new Uuid($id)), $this->admin($admin), SalesDocumentType::Quotation, new QuotationId(new Uuid(self::Q)), 1, 'application/pdf', 'Offerte-Q000001.pdf', $admin.'/sales-document-artifacts/'.$id.'.pdf', hash('sha256', '%PDF'), 4, new DateTimeImmutable('2026-08-25T12:00:00+00:00'), 'quotation-v1', 'test', $fingerprint);
    }

    private function admin(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }
}
