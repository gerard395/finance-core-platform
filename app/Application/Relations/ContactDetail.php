<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Domain\Relations\Enums\ContactStatus;
use App\Domain\Relations\ValueObjects\ContactId;
use App\Domain\Relations\ValueObjects\ContactName;
use App\Domain\Relations\ValueObjects\EmailAddress;
use App\Domain\Relations\ValueObjects\PhoneNumber;

final readonly class ContactDetail
{
    public function __construct(
        public ContactId $id,
        public ContactName $name,
        public ?EmailAddress $emailAddress,
        public ?PhoneNumber $phoneNumber,
        public ContactStatus $status,
    ) {}
}
