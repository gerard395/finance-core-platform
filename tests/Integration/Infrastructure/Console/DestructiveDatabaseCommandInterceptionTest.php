<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Console;

use App\Infrastructure\Console\DestructiveDatabaseCommandGuard;
use App\Infrastructure\Console\GuardDestructiveDatabaseCommands;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

final class DestructiveDatabaseCommandInterceptionTest extends TestCase
{
    #[Test]
    public function phpunit_runtime_and_active_connection_are_the_exact_isolated_testing_target(): void
    {
        $connection = DB::connection();
        $actual = $connection->selectOne('select database() as database_name');

        self::assertSame('testing', $this->app->environment());
        self::assertSame('testing', $connection->getConfig('database'));
        self::assertSame('testing', $actual->database_name ?? null);
        self::assertTrue(Event::hasListeners(CommandStarting::class));

        $listener = $this->app->make(GuardDestructiveDatabaseCommands::class);
        $listener->ensureCurrentTargetAllowed('migrate:fresh');
        $listener(new CommandStarting('migrate:fresh', new ArrayInput([]), new BufferedOutput));
        self::addToAssertionCount(1);
    }

    #[Test]
    public function configured_protected_command_inventory_is_exact(): void
    {
        self::assertSame([
            'db:wipe',
            'migrate:fresh',
            'migrate:refresh',
            'migrate:reset',
            'migrate:rollback',
        ], $this->app->make(DestructiveDatabaseCommandGuard::class)->protectedCommands());
    }
}
