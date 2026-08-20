<?php

declare(strict_types=1);

namespace App\Http\Auth;

use App\Application\Identity\UserRepository;
use App\Domain\Identity\Entities\User as DomainUser;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Identity\Uuid;
use App\Models\User as AuthUser;
use InvalidArgumentException;

final readonly class DomainUserResolver
{
    public function __construct(private UserRepository $users) {}

    public function resolve(AuthUser $authUser): ?DomainUser
    {
        $value = $authUser->getAttribute('domain_user_id');

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return $this->users->findById(new UserId(new Uuid($value)));
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
