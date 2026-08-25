<?php

declare(strict_types=1);

namespace App\Application\Sales;

use InvalidArgumentException;

final readonly class RenderedSalesDocument
{
    public function __construct(public string $bytes, public string $rendererVersion)
    {
        if (! str_starts_with($bytes, '%PDF') || $bytes === '') {
            throw new InvalidArgumentException('Renderer did not return a PDF document.');
        }
    }
}
