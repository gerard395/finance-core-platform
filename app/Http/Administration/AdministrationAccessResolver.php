<?php

declare(strict_types=1);

namespace App\Http\Administration;

use App\Application\Administration\AdministrationRepository;
use App\Application\Identity\AdministrationMembershipRepository;
use App\Application\Identity\AuthorizationReadRepository;
use App\Domain\Administration\Entities\Administration;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Identity\Entities\User;
use DateTimeImmutable;

final readonly class AdministrationAccessResolver
{
    public function __construct(
        private AdministrationRepository $administrations,
        private AdministrationMembershipRepository $memberships,
        private AuthorizationReadRepository $authorization,
    ) {}

    /** @return list<Administration> */
    public function accessibleAdministrations(User $user, DateTimeImmutable $at): array
    {
        $administrations = [];

        foreach ($this->memberships->findForUser($user->id()) as $membership) {
            if (! $membership->isValidAt($at)) {
                continue;
            }

            $administration = $this->administrations->findById($membership->administrationId());
            if ($administration !== null && $administration->isActive()) {
                $administrations[] = $administration;
            }
        }

        return $administrations;
    }

    public function resolve(User $user, AdministrationId $administrationId, DateTimeImmutable $at): ?ActiveAdministrationContext
    {
        $administration = $this->administrations->findById($administrationId);
        $membership = $this->memberships->findByUserAndAdministration($user->id(), $administrationId);

        if ($administration === null || ! $administration->isActive() || $membership === null || ! $membership->isValidAt($at)) {
            return null;
        }

        return new ActiveAdministrationContext(
            $user,
            $administration,
            $this->authorization->effectivePermissionIds($user->id(), $administrationId, $at),
        );
    }
}
