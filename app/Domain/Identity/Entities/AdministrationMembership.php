<?php

declare(strict_types=1);

namespace App\Domain\Identity\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Identity\ValueObjects\AdministrationMembershipId;
use App\Domain\Identity\ValueObjects\UserId;
use DateTimeImmutable;
use InvalidArgumentException;

final class AdministrationMembership
{
    public function __construct(
        private readonly AdministrationMembershipId $id,
        private readonly UserId $userId,
        private readonly AdministrationId $administrationId,
        private bool $active,
        private readonly DateTimeImmutable $validFrom,
        private readonly DateTimeImmutable $validUntil,
    ) {
        if ($validUntil < $validFrom) {
            throw new InvalidArgumentException('Valid until cannot precede valid from.');
        }
    }

    public function id(): AdministrationMembershipId
    {
        return $this->id;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function administrationId(): AdministrationId
    {
        return $this->administrationId;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function validFrom(): DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function validUntil(): DateTimeImmutable
    {
        return $this->validUntil;
    }

    public function activate(): void
    {
        $this->active = true;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    public function isValidAt(DateTimeImmutable $moment): bool
    {
        return $this->active
            && $moment >= $this->validFrom
            && $moment <= $this->validUntil;
    }
}
