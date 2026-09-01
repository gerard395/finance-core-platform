<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

final readonly class DestructiveDatabaseCommandGuard
{
    public const string ALLOWED_DATABASE = 'testing';

    /** @var list<string> */
    private const array PROTECTED_COMMANDS = [
        'db:wipe',
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'migrate:rollback',
    ];

    public function protects(string $command): bool
    {
        return in_array($command, self::PROTECTED_COMMANDS, true);
    }

    /** @return list<string> */
    public function protectedCommands(): array
    {
        return self::PROTECTED_COMMANDS;
    }

    public function ensureAllowed(string $command, string $environment, ?string $configuredDatabase, ?string $actualDatabase): void
    {
        if (! $this->protects($command)) {
            return;
        }

        if ($environment === 'testing'
            && $configuredDatabase === self::ALLOWED_DATABASE
            && $actualDatabase === self::ALLOWED_DATABASE) {
            return;
        }

        throw new DestructiveDatabaseCommandBlocked(sprintf(
            "Destructive database command blocked: %s; environment '%s'; configured database '%s'; actual database '%s'. Allowed destructive target: '%s' in environment 'testing'.",
            $command,
            $environment !== '' ? $environment : 'unknown',
            $configuredDatabase !== null && $configuredDatabase !== '' ? $configuredDatabase : 'unknown',
            $actualDatabase !== null && $actualDatabase !== '' ? $actualDatabase : 'unknown',
            self::ALLOWED_DATABASE,
        ));
    }
}
