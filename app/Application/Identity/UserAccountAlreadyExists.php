<?php

declare(strict_types=1);

namespace App\Application\Identity;

use RuntimeException;

final class UserAccountAlreadyExists extends RuntimeException
{
    public static function forDomainUserId(): self
    {
        return new self('A user account with this Domain User identity already exists.');
    }

    public static function forEmail(): self
    {
        return new self('A user account with this email address already exists.');
    }
}
