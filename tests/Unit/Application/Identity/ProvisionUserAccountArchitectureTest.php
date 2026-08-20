<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Identity;

use App\Application\Identity\AuthAccountStore;
use App\Application\Identity\PasswordHasher;
use App\Application\Identity\ProvisionUserAccount;
use App\Application\Identity\UserRepository;
use App\Application\Shared\TransactionManager;
use App\Domain\Identity\Entities\User;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ProvisionUserAccountArchitectureTest extends TestCase
{
    public function test_application_use_case_depends_only_on_explicit_ports(): void
    {
        $constructor = (new ReflectionClass(ProvisionUserAccount::class))->getConstructor();
        $dependencies = array_map(
            static fn ($parameter): string => $parameter->getType()->getName(),
            $constructor->getParameters(),
        );

        self::assertSame([
            UserRepository::class,
            AuthAccountStore::class,
            PasswordHasher::class,
            TransactionManager::class,
        ], $dependencies);
    }

    public function test_domain_user_has_no_authentication_state(): void
    {
        $properties = array_map(
            static fn ($property): string => $property->getName(),
            (new ReflectionClass(User::class))->getProperties(),
        );

        self::assertNotContains('password', $properties);
        self::assertNotContains('rememberToken', $properties);
        self::assertNotContains('session', $properties);
    }
}
