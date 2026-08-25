<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Administration\ValueObjects\AdministrationId;

final readonly class RenderModelSalesDocumentReadinessChecker implements SalesDocumentReadinessChecker
{
    public function __construct(private SalesDocumentRenderModelBuilder $models) {}

    public function check(AdministrationId $administrationId, SalesDocumentSource $source): PrepareSalesDocumentArtifactStatus
    {
        $result = $this->models->build($administrationId, $source);

        return $result instanceof SalesDocumentRenderModel ? PrepareSalesDocumentArtifactStatus::Success : $result;
    }
}
