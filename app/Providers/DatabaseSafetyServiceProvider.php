<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Console\GuardDestructiveDatabaseCommands;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class DatabaseSafetyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(CommandStarting::class, GuardDestructiveDatabaseCommands::class);
    }
}
