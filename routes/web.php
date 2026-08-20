<?php

use App\Domain\Identity\Definitions\RelationsPermission;
use App\Http\Controllers\AdministrationSelectionController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Relations\RelationIndexController;
use App\Http\Middleware\EnsureRelationsPermission;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'domain.active'])->group(function (): void {
    Route::get('/administrations/select', [AdministrationSelectionController::class, 'create'])
        ->name('administrations.select');
    Route::post('/administrations/select', [AdministrationSelectionController::class, 'store'])
        ->name('administrations.select.store');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

Route::get('/app', DashboardController::class)
    ->middleware(['auth', 'domain.active', 'administration.active'])
    ->name('app');

Route::get('/relations', RelationIndexController::class)
    ->middleware([
        'auth',
        'domain.active',
        'administration.active',
        EnsureRelationsPermission::using(RelationsPermission::View),
    ])
    ->name('relations.index');
