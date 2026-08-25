<?php

declare(strict_types=1);

namespace App\Application\Sales;

interface SalesDocumentPdfRenderer
{
    public function render(SalesDocumentRenderModel $model): RenderedSalesDocument;
}
