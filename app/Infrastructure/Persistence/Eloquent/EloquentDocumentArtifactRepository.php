<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Sales\DocumentArtifactRepository;
use App\Application\Sales\SalesDocumentSource;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Entities\DocumentArtifact;
use App\Domain\Sales\Enums\SalesDocumentType;
use App\Domain\Sales\ValueObjects\ArtifactId;
use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class EloquentDocumentArtifactRepository implements DocumentArtifactRepository
{
    public function findBySourceFingerprint(AdministrationId $administrationId, SalesDocumentSource $source, string $fingerprint): ?DocumentArtifact
    {
        [$table, $column] = $this->link($source->type);
        $row = DB::table($table.' as link')->join('document_artifacts as artifact', function ($join): void {
            $join->on('artifact.id', '=', 'link.artifact_id')->on('artifact.administration_id', '=', 'link.administration_id');
        })->where('link.administration_id', $administrationId->toString())->where('link.'.$column, $source->id)->where('link.render_fingerprint', $fingerprint)->select('artifact.*', 'link.'.$column.' as source_id')->first();

        return $row === null ? null : $this->hydrate($row);
    }

    public function find(AdministrationId $administrationId, ArtifactId $artifactId): ?DocumentArtifact
    {
        $artifact = DB::table('document_artifacts')->where('administration_id', $administrationId->toString())->where('id', $artifactId->toString())->first();
        if ($artifact === null) {
            return null;
        }
        [$table, $column] = $this->link(SalesDocumentType::from($artifact->document_type));
        $link = DB::table($table)->where('administration_id', $administrationId->toString())->where('artifact_id', $artifactId->toString())->first();
        if ($link === null) {
            return null;
        }
        $artifact->source_id = $link->{$column};

        return $this->hydrate($artifact);
    }

    public function nextVersion(AdministrationId $administrationId, SalesDocumentSource $source): int
    {
        [$table, $column] = $this->link($source->type);

        return ((int) DB::table($table)->where('administration_id', $administrationId->toString())->where($column, $source->id)->max('version')) + 1;
    }

    public function persist(DocumentArtifact $artifact): DocumentArtifact
    {
        [$table, $column] = $this->link($artifact->documentType);
        $source = new SalesDocumentSourceForPersistence($artifact->documentType, $artifact->sourceId->toString());
        try {
            DB::transaction(function () use ($artifact, $table, $column): void {
                $now = now();
                DB::table('document_artifacts')->insert(['id' => $artifact->id->toString(), 'administration_id' => $artifact->administrationId->toString(), 'document_type' => $artifact->documentType->value, 'version' => $artifact->version, 'mime_type' => $artifact->mimeType, 'filename' => $artifact->filename, 'storage_key' => $artifact->storageKey, 'sha256' => $artifact->sha256, 'byte_size' => $artifact->byteSize, 'generated_at' => $artifact->generatedAt, 'template_version' => $artifact->templateVersion, 'renderer_version' => $artifact->rendererVersion, 'render_fingerprint' => $artifact->renderFingerprint, 'locale' => $artifact->locale, 'created_at' => $now, 'updated_at' => $now]);
                DB::table($table)->insert(['artifact_id' => $artifact->id->toString(), 'administration_id' => $artifact->administrationId->toString(), $column => $artifact->sourceId->toString(), 'render_fingerprint' => $artifact->renderFingerprint, 'version' => $artifact->version, 'created_at' => $now, 'updated_at' => $now]);
            }, 3);
        } catch (QueryException $exception) {
            $existing = $this->findBySourceFingerprint($artifact->administrationId, $source->toPublicSource(), $artifact->renderFingerprint);
            if ($existing !== null) {
                return $existing;
            }
            throw $exception;
        }

        return $artifact;
    }

    /** @return array{string, string} */
    private function link(SalesDocumentType $type): array
    {
        return match ($type) {
            SalesDocumentType::Quotation => ['quotation_document_artifacts', 'quotation_id'], SalesDocumentType::SalesInvoice => ['sales_invoice_document_artifacts', 'sales_invoice_id'], SalesDocumentType::SalesCreditInvoice => ['sales_credit_invoice_document_artifacts', 'sales_credit_invoice_id']
        };
    }

    private function hydrate(object $row): DocumentArtifact
    {
        $type = SalesDocumentType::from($row->document_type);
        $uuid = new Uuid($row->source_id);
        $sourceId = match ($type) {
            SalesDocumentType::Quotation => new QuotationId($uuid),
            SalesDocumentType::SalesInvoice => new SalesInvoiceId($uuid),
            SalesDocumentType::SalesCreditInvoice => new SalesCreditInvoiceId($uuid),
        };

        return new DocumentArtifact(new ArtifactId(new Uuid($row->id)), new AdministrationId(new Uuid($row->administration_id)), $type, $sourceId, (int) $row->version, $row->mime_type, $row->filename, $row->storage_key, $row->sha256, (int) $row->byte_size, new DateTimeImmutable($row->generated_at), $row->template_version, $row->renderer_version, $row->render_fingerprint, $row->locale);
    }
}
