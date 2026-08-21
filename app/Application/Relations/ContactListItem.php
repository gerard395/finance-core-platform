<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Domain\Relations\Enums\ContactStatus;
use App\Domain\Relations\ValueObjects\ContactId;
use App\Domain\Relations\ValueObjects\ContactName;

final readonly class ContactListItem
{
    public function __construct(
        public ContactId $id,
        public ContactName $name,
        public ContactStatus $status,
    ) {}
}
