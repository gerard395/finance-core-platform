<?php

declare(strict_types=1);

namespace App\Domain\Banking\ValueObjects;

final readonly class CanonicalizationVersion
{
    public const string CURRENT = 'bir-canonical-entry-v1';

    public function __construct(public string $value = self::CURRENT) {}
}
