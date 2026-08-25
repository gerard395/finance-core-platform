<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Sales;

use App\Application\Sales\SalesDocumentPdfRenderer;
use App\Application\Sales\SalesDocumentRenderModel;
use App\Domain\Sales\Enums\SalesDocumentType;
use Tests\TestCase;

final class BrowsershotSalesDocumentPdfRendererTest extends TestCase
{
    public function test_renderer_produces_non_empty_pdf_without_executing_user_html(): void
    {
        $model = new SalesDocumentRenderModel(SalesDocumentType::Quotation, 'a1000000-0000-4000-8000-000000000001', 'Q000001', 'quotation-v1', [
            'document' => ['number' => 'Q000001', 'date' => '2026-08-25', 'valid_until' => null, 'currency' => 'EUR'],
            'customer' => ['number' => 'C000001', 'name' => '<script>document.write("unsafe")</script>', 'address' => ['line_1' => 'Straat 1', 'line_2' => null, 'postal_code' => '1234AB', 'city' => 'Amsterdam', 'country' => 'NL']],
            'issuer' => ['name' => 'Demo B.V.', 'display_name' => 'Demo', 'line_1' => 'Laan 1', 'line_2' => null, 'postal_code' => '1000AA', 'city' => 'Amsterdam', 'country' => 'NL', 'registration_number' => '12345678', 'business_email' => null, 'business_phone' => null, 'website' => null, 'iban' => null, 'bic' => null, 'account_holder' => null],
            'lines' => [['description' => '<img src="https://invalid.example/x"> Advies', 'quantity' => '1', 'unit_price' => '100', 'net' => '100']],
            'totals' => ['net' => '100'],
        ]);
        $rendered = $this->app->make(SalesDocumentPdfRenderer::class)->render($model);

        self::assertStringStartsWith('%PDF', $rendered->bytes);
        self::assertGreaterThan(1000, strlen($rendered->bytes));
        self::assertStringContainsString('chrome-152.0.7977.42', $rendered->rendererVersion);
    }
}
