<?php

declare(strict_types=1);

namespace App\Domain\Sales\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Enums\SalesDocumentType;
use App\Domain\Sales\ValueObjects\ArtifactId;
use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class DocumentArtifact
{
    public function __construct(
        public ArtifactId $id,
        public AdministrationId $administrationId,
        public SalesDocumentType $documentType,
        public QuotationId|SalesInvoiceId|SalesCreditInvoiceId $sourceId,
        public int $version,
        public string $mimeType,
        public string $filename,
        public string $storageKey,
        public string $sha256,
        public int $byteSize,
        public DateTimeImmutable $generatedAt,
        public string $templateVersion,
        public string $rendererVersion,
        public string $renderFingerprint,
        public string $locale = 'nl',
    ) {
        if ($version < 1 || $byteSize < 1 || $mimeType !== 'application/pdf') {
            throw new InvalidArgumentException('Document artifact metadata is invalid.');
        }
        $sourceMatchesType = match ($documentType) {
            SalesDocumentType::Quotation => $sourceId instanceof QuotationId,
            SalesDocumentType::SalesInvoice => $sourceId instanceof SalesInvoiceId,
            SalesDocumentType::SalesCreditInvoice => $sourceId instanceof SalesCreditInvoiceId,
        };
        if (! $sourceMatchesType) {
            throw new InvalidArgumentException('Document artifact source identity does not match its document type.');
        }
        if (preg_match('/\A[a-f0-9]{64}\z/', $sha256) !== 1 || preg_match('/\A[a-f0-9]{64}\z/', $renderFingerprint) !== 1) {
            throw new InvalidArgumentException('Document artifact hashes must be normalized SHA-256 values.');
        }
        if (str_contains($storageKey, '..') || str_starts_with($storageKey, '/') || str_contains($storageKey, '\\')) {
            throw new InvalidArgumentException('Document artifact storage key is unsafe.');
        }
    }
}
