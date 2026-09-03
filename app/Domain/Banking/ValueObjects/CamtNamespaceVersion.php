<?php

declare(strict_types=1);

namespace App\Domain\Banking\ValueObjects;

enum CamtNamespaceVersion: string
{
    case V02 = 'urn:iso:std:iso:20022:tech:xsd:camt.053.001.02';
    case V08 = 'urn:iso:std:iso:20022:tech:xsd:camt.053.001.08';

    public function parserVersion(): string
    {
        return match ($this) {
            self::V02 => 'camt053-00102-parser-v1',
            self::V08 => 'camt053-00108-parser-v1',
        };
    }
}
