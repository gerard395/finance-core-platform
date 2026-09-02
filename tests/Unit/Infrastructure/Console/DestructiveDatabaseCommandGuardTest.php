<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Console;

use App\Infrastructure\Console\DestructiveDatabaseCommandBlocked;
use App\Infrastructure\Console\DestructiveDatabaseCommandGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DestructiveDatabaseCommandGuardTest extends TestCase
{
    #[Test]
    public function exact_incident_is_blocked_before_destructive_execution(): void
    {
        $executed = false;

        try {
            (new DestructiveDatabaseCommandGuard)->ensureAllowed('migrate:fresh', 'testing', 'laravel', 'laravel');
            $executed = true;
            self::fail('The incident target was not blocked.');
        } catch (DestructiveDatabaseCommandBlocked $exception) {
            self::assertStringContainsString('migrate:fresh', $exception->getMessage());
            self::assertStringContainsString("environment 'testing'", $exception->getMessage());
            self::assertStringContainsString("configured database 'laravel'", $exception->getMessage());
            self::assertStringContainsString("actual database 'laravel'", $exception->getMessage());
            self::assertStringContainsString("Allowed destructive target: 'testing'", $exception->getMessage());
        }

        self::assertFalse($executed);
    }

    #[Test]
    #[DataProvider('protectedCommands')]
    public function every_protected_command_requires_exact_testing_target(string $command): void
    {
        $guard = new DestructiveDatabaseCommandGuard;
        self::assertTrue($guard->protects($command));
        $guard->ensureAllowed($command, 'testing', 'testing', 'testing');
        self::addToAssertionCount(1);
    }

    /** @return iterable<string, array{string}> */
    public static function protectedCommands(): iterable
    {
        foreach (['migrate:fresh', 'migrate:refresh', 'migrate:reset', 'migrate:rollback', 'db:wipe'] as $command) {
            yield $command => [$command];
        }
    }

    #[Test]
    #[DataProvider('unsafeTargets')]
    public function mismatched_or_unknown_targets_fail_closed(string $environment, ?string $configured, ?string $actual): void
    {
        $this->expectException(DestructiveDatabaseCommandBlocked::class);

        (new DestructiveDatabaseCommandGuard)->ensureAllowed('migrate:fresh', $environment, $configured, $actual);
    }

    /** @return iterable<string, array{string, ?string, ?string}> */
    public static function unsafeTargets(): iterable
    {
        yield 'wrong environment' => ['local', 'testing', 'testing'];
        yield 'configured testing actual development' => ['testing', 'testing', 'laravel'];
        yield 'configured development actual testing' => ['testing', 'laravel', 'testing'];
        yield 'unknown configured database' => ['testing', null, 'testing'];
        yield 'empty configured database' => ['testing', '', 'testing'];
        yield 'unknown actual database' => ['testing', 'testing', null];
        yield 'empty actual database' => ['testing', 'testing', ''];
        yield 'similar database name is not allowlisted' => ['testing', 'testing-copy', 'testing-copy'];
    }

    #[Test]
    #[DataProvider('normalCommands')]
    public function normal_commands_are_not_intercepted(string $command): void
    {
        $guard = new DestructiveDatabaseCommandGuard;

        self::assertFalse($guard->protects($command));
        $guard->ensureAllowed($command, 'local', 'laravel', 'laravel');
        self::addToAssertionCount(1);
    }

    /** @return iterable<string, array{string}> */
    public static function normalCommands(): iterable
    {
        yield 'migrate' => ['migrate'];
        yield 'migrate status' => ['migrate:status'];
        yield 'application runtime command' => ['queue:work'];
    }
}
