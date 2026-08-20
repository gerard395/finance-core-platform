<?php

declare(strict_types=1);

namespace App\Application\Identity;

use SensitiveParameter;

interface PasswordHasher
{
    public function hash(#[SensitiveParameter] string $plainTextPassword): string;
}
