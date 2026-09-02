<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Throwable;

final readonly class GuardDestructiveDatabaseCommands
{
    public function __construct(
        private Application $application,
        private DatabaseManager $databases,
        private DestructiveDatabaseCommandGuard $guard,
    ) {}

    public function __invoke(CommandStarting $event): void
    {
        $this->ensureCurrentTargetAllowed($event->command);
    }

    public function ensureCurrentTargetAllowed(string $command): void
    {
        if (! $this->guard->protects($command)) {
            return;
        }

        $configuredDatabase = null;
        $actualDatabase = null;

        try {
            $connection = $this->databases->connection($this->databases->getDefaultConnection());
            $configured = $connection->getConfig('database');
            $configuredDatabase = is_string($configured) ? $configured : null;
            $metadata = $connection->selectOne('select database() as database_name');
            $actualDatabase = is_object($metadata) && is_string($metadata->database_name ?? null)
                ? $metadata->database_name
                : null;
        } catch (Throwable) {
            // The guard deliberately fails closed when the target cannot be proven.
        }

        $this->guard->ensureAllowed(
            $command,
            $this->application->environment(),
            $configuredDatabase,
            $actualDatabase,
        );
    }
}
