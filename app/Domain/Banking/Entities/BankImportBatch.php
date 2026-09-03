<?php

declare(strict_types=1);

namespace App\Domain\Banking\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\ValueObjects\AdministrationBankAccountId;
use App\Domain\Banking\ValueObjects\BankImportBatchId;
use App\Domain\Banking\ValueObjects\CamtNamespaceVersion;
use App\Domain\Banking\ValueObjects\CanonicalizationVersion;
use App\Domain\Banking\ValueObjects\OriginalFileHash;
use App\Domain\Banking\ValueObjects\SourceFormat;
use App\Domain\Identity\ValueObjects\UserId;
use DateTimeImmutable;

final readonly class BankImportBatch
{
    /** @param list<BankStatement> $statements */
    public function __construct(public BankImportBatchId $id, public AdministrationId $administrationId, public AdministrationBankAccountId $bankAccountId, public SourceFormat $sourceFormat, public CamtNamespaceVersion $namespaceVersion, public OriginalFileHash $originalFileHash, public string $parserVersion, public CanonicalizationVersion $canonicalizationVersion, public UserId $actorId, public DateTimeImmutable $importedAt, public string $artifactReference, public array $statements) {}
}
