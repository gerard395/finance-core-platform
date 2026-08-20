<?php

use App\Http\Controllers\AdministrationSelectionController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\AuthenticatedPlaceholderController;
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

Route::get('/app', AuthenticatedPlaceholderController::class)
    ->middleware(['auth', 'domain.active', 'administration.active'])
    ->name('app');
