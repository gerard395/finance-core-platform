<?php

declare(strict_types=1);

namespace App\Domain\Banking\ValueObjects;

enum SourceFormat: string
{
    case Camt053 = 'camt.053';
}
