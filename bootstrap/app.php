<?php

use App\Http\Middleware\EnsureActiveAdministration;
use App\Http\Middleware\EnsureActiveDomainUser;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'domain.active' => EnsureActiveDomainUser::class,
            'administration.active' => EnsureActiveAdministration::class,
        ]);
        $middleware->redirectUsersTo(fn (): string => route('app'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
