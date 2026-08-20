<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Domain\Identity\Entities\User;
use App\Domain\Identity\ValueObjects\UserId;

interface UserRepository
{
    public function findById(UserId $id): ?User;

    public function save(User $user): void;
}
