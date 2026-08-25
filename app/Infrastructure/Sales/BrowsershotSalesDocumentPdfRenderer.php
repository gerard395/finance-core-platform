<?php

declare(strict_types=1);

namespace App\Infrastructure\Sales;

use App\Application\Sales\RenderedSalesDocument;
use App\Application\Sales\SalesDocumentPdfRenderer;
use App\Application\Sales\SalesDocumentRenderModel;
use App\Domain\Sales\Enums\SalesDocumentType;
use Illuminate\Contracts\View\Factory;
use Spatie\Browsershot\Browsershot;

final readonly class BrowsershotSalesDocumentPdfRenderer implements SalesDocumentPdfRenderer
{
    public function __construct(private Factory $views) {}

    public function render(SalesDocumentRenderModel $model): RenderedSalesDocument
    {
        $view = match ($model->type) {
            SalesDocumentType::Quotation => 'pdf.sales.quotation', SalesDocumentType::SalesInvoice => 'pdf.sales.invoice', SalesDocumentType::SalesCreditInvoice => 'pdf.sales.credit-invoice'
        };
        $html = $this->views->make($view, ['model' => $model])->render();
        $pdf = Browsershot::html($html)
            ->setNodeModulePath(base_path('node_modules'))
            ->disableJavascript()
            ->disableRedirects()
            ->blockUrls(['http://*', 'https://*', 'file://*'])
            ->noSandbox()
            ->format('A4')
            ->margins(12, 12, 14, 12)
            ->timeout(30)
            ->pdf();

        return new RenderedSalesDocument($pdf, 'browsershot-5.2.0/chrome-152.0.7977.42');
    }
}
