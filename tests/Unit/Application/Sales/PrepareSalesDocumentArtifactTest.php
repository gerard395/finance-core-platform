<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Sales;

use App\Application\Sales\DocumentArtifactFailureReporter;
use App\Application\Sales\DocumentArtifactRepository;
use App\Application\Sales\DocumentArtifactStorage;
use App\Application\Sales\PrepareSalesDocumentArtifact;
use App\Application\Sales\PrepareSalesDocumentArtifactStatus;
use App\Application\Sales\QuotationReadRepository;
use App\Application\Sales\RenderedSalesDocument;
use App\Application\Sales\SalesCreditInvoiceReadRepository;
use App\Application\Sales\SalesDocumentArtifactIdentityGenerator;
use App\Application\Sales\SalesDocumentIssuer;
use App\Application\Sales\SalesDocumentIssuerReader;
use App\Application\Sales\SalesDocumentIssuerReadiness;
use App\Application\Sales\SalesDocumentPdfRenderer;
use App\Application\Sales\SalesDocumentRenderModel;
use App\Application\Sales\SalesDocumentRenderModelBuilder;
use App\Application\Sales\SalesDocumentSource;
use App\Application\Sales\SalesInvoiceReadRepository;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Services\TaxCalculation;
use App\Domain\Relations\Enums\AddressType;
use App\Domain\Relations\ValueObjects\AddressId;
use App\Domain\Relations\ValueObjects\AddressLine;
use App\Domain\Relations\ValueObjects\City;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Relations\ValueObjects\CustomerNumber;
use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Relations\ValueObjects\PostalCode;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Sales\Entities\DocumentArtifact;
use App\Domain\Sales\Entities\Quotation;
use App\Domain\Sales\Entities\QuotationLine;
use App\Domain\Sales\Entities\SalesCreditInvoice;
use App\Domain\Sales\Entities\SalesInvoice;
use App\Domain\Sales\Enums\QuotationStatus;
use App\Domain\Sales\Services\SalesFiscalWordingPolicy;
use App\Domain\Sales\ValueObjects\ArtifactId;
use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Sales\ValueObjects\QuotationLineId;
use App\Domain\Sales\ValueObjects\QuotationNumber;
use App\Domain\Sales\ValueObjects\SalesAddressSnapshot;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;
use App\Domain\Sales\ValueObjects\SalesCustomerSnapshot;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Shared\Commerce\ValueObjects\LineDescription;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PrepareSalesDocumentArtifactTest extends TestCase
{
    public function test_store_hash_reuse_integrity_and_changed_issuer_create_immutable_new_version(): void
    {
        $admin = new AdministrationId($this->uuid(1));
        $quotation = $this->quotation($admin);
        $quotations = new class($quotation) implements QuotationReadRepository
        {
            public function __construct(private Quotation $quotation) {}

            public function findForAdministration(AdministrationId $administrationId, QuotationId $quotationId): ?Quotation
            {
                return $this->quotation;
            }
        };
        $invoices = new class implements SalesInvoiceReadRepository
        {
            public function findForAdministration(AdministrationId $administrationId, SalesInvoiceId $invoiceId): ?SalesInvoice
            {
                return null;
            }
        };
        $credits = new class implements SalesCreditInvoiceReadRepository
        {
            public function findForAdministration(AdministrationId $administrationId, SalesCreditInvoiceId $invoiceId): ?SalesCreditInvoice
            {
                return null;
            }
        };
        $currentIssuer = $this->issuer('Demo B.V.');
        $issuers = new class($currentIssuer) implements SalesDocumentIssuerReader
        {
            public function __construct(public SalesDocumentIssuer $current) {}

            public function readIssuer(AdministrationId $administrationId): ?SalesDocumentIssuer
            {
                return $this->current;
            }
        };
        $builder = new SalesDocumentRenderModelBuilder($quotations, $invoices, $credits, $issuers, new SalesDocumentIssuerReadiness($issuers), new TaxCalculation, new SalesFiscalWordingPolicy);
        $renderer = new class implements SalesDocumentPdfRenderer
        {
            public function render(SalesDocumentRenderModel $model): RenderedSalesDocument
            {
                return new RenderedSalesDocument('%PDF-1.4 '.json_encode($model->content, JSON_THROW_ON_ERROR), 'deterministic-test-renderer');
            }
        };
        $storage = new class implements DocumentArtifactStorage
        {
            /** @var array<string, string> */
            public array $bytes = [];

            public function store(string $storageKey, string $bytes): void
            {
                $this->bytes[$storageKey] = $bytes;
            }

            public function read(string $storageKey): ?string
            {
                return $this->bytes[$storageKey] ?? null;
            }

            public function exists(string $storageKey): bool
            {
                return isset($this->bytes[$storageKey]);
            }

            public function deleteOrphan(string $storageKey): void
            {
                unset($this->bytes[$storageKey]);
            }
        };
        $repository = new class implements DocumentArtifactRepository
        {
            /** @var list<DocumentArtifact> */
            public array $artifacts = [];

            public function findBySourceFingerprint(AdministrationId $administrationId, SalesDocumentSource $source, string $fingerprint): ?DocumentArtifact
            {
                foreach ($this->artifacts as $artifact) {
                    if ($artifact->administrationId->equals($administrationId) && $artifact->sourceId->toString() === $source->id && $artifact->renderFingerprint === $fingerprint) {
                        return $artifact;
                    }
                }

                return null;
            }

            public function find(AdministrationId $administrationId, ArtifactId $artifactId): ?DocumentArtifact
            {
                foreach ($this->artifacts as $artifact) {
                    if ($artifact->administrationId->equals($administrationId) && $artifact->id->equals($artifactId)) {
                        return $artifact;
                    }
                }

                return null;
            }

            public function nextVersion(AdministrationId $administrationId, SalesDocumentSource $source): int
            {
                return count(array_filter($this->artifacts, static fn (DocumentArtifact $artifact): bool => $artifact->administrationId->equals($administrationId) && $artifact->sourceId->toString() === $source->id)) + 1;
            }

            public function persist(DocumentArtifact $artifact): DocumentArtifact
            {
                $this->artifacts[] = $artifact;

                return $artifact;
            }
        };
        $identities = new class implements SalesDocumentArtifactIdentityGenerator
        {
            private int $next = 100;

            public function next(): ArtifactId
            {
                return new ArtifactId(new Uuid(sprintf('a1000000-0000-4000-8000-%012d', $this->next++)));
            }
        };
        $failures = new class implements DocumentArtifactFailureReporter
        {
            public function report(string $stage, AdministrationId $administrationId, ?string $storageKey = null): void {}
        };
        $prepare = new PrepareSalesDocumentArtifact($builder, $renderer, $storage, $repository, $identities, $failures);
        $source = SalesDocumentSource::quotation($quotation->id());

        $first = $prepare->execute($admin, $source);
        $reused = $prepare->execute($admin, $source);
        self::assertSame(PrepareSalesDocumentArtifactStatus::Success, $first->status);
        self::assertSame(PrepareSalesDocumentArtifactStatus::Reused, $reused->status);
        self::assertTrue($first->artifact?->id->equals($reused->artifact->id));
        $original = $storage->read($first->artifact->storageKey);
        self::assertStringStartsWith('%PDF', $original);
        self::assertSame(hash('sha256', $original), $first->artifact->sha256);
        self::assertSame(strlen($original), $first->artifact->byteSize);

        $issuers->current = $this->issuer('Nieuwe Demo B.V.');
        $changed = $prepare->execute($admin, $source);
        self::assertSame(PrepareSalesDocumentArtifactStatus::Success, $changed->status);
        self::assertSame(2, $changed->artifact?->version);
        self::assertNotSame($first->artifact->renderFingerprint, $changed->artifact?->renderFingerprint);
        self::assertSame($original, $storage->read($first->artifact->storageKey));
        self::assertCount(2, $repository->artifacts);

        $storage->bytes[$changed->artifact->storageKey] = '%PDF-corrupt';
        self::assertSame(PrepareSalesDocumentArtifactStatus::IntegrityFailure, $prepare->execute($admin, $source)->status);
    }

    private function quotation(AdministrationId $admin): Quotation
    {
        $customerId = new CustomerId($this->uuid(2));

        return Quotation::reconstitute(new QuotationId($this->uuid(3)), new QuotationNumber('Q000001'), $admin, $customerId, new Currency('EUR'), QuotationStatus::Draft, new DateTimeImmutable('2026-08-25'), null, [new QuotationLine(new QuotationLineId($this->uuid(4)), new LineDescription('Adviesdiensten'), new Quantity('2'), new Money('50', new Currency('EUR')))], new SalesCustomerSnapshot($customerId, new RelationId($this->uuid(5)), new CustomerNumber('C000001'), new DisplayName('Klant')), new SalesAddressSnapshot(new AddressId($this->uuid(6)), AddressType::Quotation, new AddressLine('Klantstraat 1'), null, new PostalCode('1000AA'), new City('Utrecht'), new CountryCode('NL')));
    }

    private function issuer(string $name): SalesDocumentIssuer
    {
        return new SalesDocumentIssuer($name, $name, new AddressLine('Issuerstraat 1'), null, new PostalCode('1234AB'), new City('Amsterdam'), new CountryCode('NL'), null, new CountryCode('NL'), '12345678', null, null, null, null, null, null);
    }

    private function uuid(int $suffix): Uuid
    {
        return new Uuid(sprintf('a1000000-0000-4000-8000-%012d', $suffix));
    }
}
