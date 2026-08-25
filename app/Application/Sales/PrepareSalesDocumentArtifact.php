<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Entities\DocumentArtifact;
use App\Domain\Sales\Enums\SalesDocumentType;
use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use Throwable;

final readonly class PrepareSalesDocumentArtifact
{
    public function __construct(
        private SalesDocumentRenderModelBuilder $models,
        private SalesDocumentPdfRenderer $renderer,
        private DocumentArtifactStorage $storage,
        private DocumentArtifactRepository $artifacts,
        private SalesDocumentArtifactIdentityGenerator $identities,
        private DocumentArtifactFailureReporter $failures,
    ) {}

    public function execute(AdministrationId $administrationId, SalesDocumentSource $source): PrepareSalesDocumentArtifactResult
    {
        $model = $this->models->build($administrationId, $source);
        if ($model instanceof PrepareSalesDocumentArtifactStatus) {
            return new PrepareSalesDocumentArtifactResult($model);
        }
        $fingerprint = $model->fingerprint();
        $existing = $this->artifacts->findBySourceFingerprint($administrationId, $source, $fingerprint);
        if ($existing !== null) {
            $bytes = $this->storage->read($existing->storageKey);
            if ($bytes === null || hash('sha256', $bytes) !== $existing->sha256) {
                return new PrepareSalesDocumentArtifactResult(PrepareSalesDocumentArtifactStatus::IntegrityFailure);
            }

            return new PrepareSalesDocumentArtifactResult(PrepareSalesDocumentArtifactStatus::Reused, $existing);
        }
        try {
            $rendered = $this->renderer->render($model);
        } catch (Throwable) {
            $this->failures->report('render', $administrationId);

            return new PrepareSalesDocumentArtifactResult(PrepareSalesDocumentArtifactStatus::RenderingFailure);
        }
        $id = $this->identities->next();
        $storageKey = $administrationId->toString().'/sales-document-artifacts/'.$id->toString().'.pdf';
        $filename = $this->filename($model);
        $sourceId = match ($source->type) {
            SalesDocumentType::Quotation => new QuotationId(new Uuid($source->id)),
            SalesDocumentType::SalesInvoice => new SalesInvoiceId(new Uuid($source->id)),
            SalesDocumentType::SalesCreditInvoice => new SalesCreditInvoiceId(new Uuid($source->id)),
        };
        $artifact = new DocumentArtifact($id, $administrationId, $source->type, $sourceId, $this->artifacts->nextVersion($administrationId, $source), 'application/pdf', $filename, $storageKey, hash('sha256', $rendered->bytes), strlen($rendered->bytes), new DateTimeImmutable, $model->templateVersion, $rendered->rendererVersion, $fingerprint);
        try {
            $this->storage->store($storageKey, $rendered->bytes);
        } catch (Throwable) {
            $this->failures->report('storage', $administrationId, $storageKey);

            return new PrepareSalesDocumentArtifactResult(PrepareSalesDocumentArtifactStatus::StorageFailure);
        }
        try {
            $persisted = $this->artifacts->persist($artifact);
            if (! $persisted->id->equals($artifact->id)) {
                $this->storage->deleteOrphan($storageKey);
            }

            return new PrepareSalesDocumentArtifactResult($persisted->id->equals($artifact->id) ? PrepareSalesDocumentArtifactStatus::Success : PrepareSalesDocumentArtifactStatus::Reused, $persisted);
        } catch (Throwable $exception) {
            try {
                $this->storage->deleteOrphan($storageKey);
            } catch (Throwable) {
                $this->failures->report('orphan_cleanup', $administrationId, $storageKey);
            }

            $this->failures->report('persistence', $administrationId, $storageKey);

            return new PrepareSalesDocumentArtifactResult(PrepareSalesDocumentArtifactStatus::PersistenceFailure);
        }
    }

    private function filename(SalesDocumentRenderModel $model): string
    {
        $label = match ($model->type) {
            SalesDocumentType::Quotation => 'Offerte', SalesDocumentType::SalesInvoice => 'Factuur', SalesDocumentType::SalesCreditInvoice => 'Creditfactuur'
        };
        $number = preg_replace('/[^A-Za-z0-9_-]/', '-', $model->number) ?: 'document';

        return $label.'-'.$number.'.pdf';
    }
}
