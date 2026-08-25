<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Sales;

use App\Application\Sales\SalesDocumentRenderModel;
use App\Domain\Sales\Enums\SalesDocumentType;
use PHPUnit\Framework\TestCase;

final class SalesDocumentRenderModelTest extends TestCase
{
    public function test_fingerprint_is_canonical_and_ignores_array_insertion_order(): void
    {
        $a = new SalesDocumentRenderModel(SalesDocumentType::SalesInvoice, 'a1000000-0000-4000-8000-000000000001', 'F000001', 'sales-invoice-v1', ['issuer' => ['name' => 'A', 'city' => 'B'], 'total' => '10']);
        $b = new SalesDocumentRenderModel(SalesDocumentType::SalesInvoice, 'a1000000-0000-4000-8000-000000000001', 'F000001', 'sales-invoice-v1', ['total' => '10', 'issuer' => ['city' => 'B', 'name' => 'A']]);
        $changed = new SalesDocumentRenderModel(SalesDocumentType::SalesInvoice, 'a1000000-0000-4000-8000-000000000001', 'F000001', 'sales-invoice-v1', ['issuer' => ['name' => 'A', 'city' => 'C'], 'total' => '10']);

        self::assertSame($a->fingerprint(), $b->fingerprint());
        self::assertNotSame($a->fingerprint(), $changed->fingerprint());
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $a->fingerprint());
    }
}
