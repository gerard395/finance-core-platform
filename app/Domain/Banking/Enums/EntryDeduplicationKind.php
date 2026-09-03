<?php

declare(strict_types=1);

namespace App\Domain\Banking\Enums;

enum EntryDeduplicationKind: string
{
    case AccountServicerReference = 'account_servicer_reference';
    case EntryReference = 'entry_reference';
    case CanonicalHash = 'canonical_hash';
}
