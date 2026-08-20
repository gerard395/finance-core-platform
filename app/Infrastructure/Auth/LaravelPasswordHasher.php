<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth;

use App\Application\Identity\PasswordHasher;
use Illuminate\Contracts\Hashing\Hasher;
use SensitiveParameter;

final readonly class LaravelPasswordHasher implements PasswordHasher
{
    public function __construct(
        private Hasher $hasher,
    ) {}

    public function hash(#[SensitiveParameter] string $plainTextPassword): string
    {
        return $this->hasher->make($plainTextPassword);
    }
}
