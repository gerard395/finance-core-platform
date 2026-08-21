<?php

use App\Domain\Identity\Definitions\RelationsPermission;
use App\Http\Controllers\AdministrationSelectionController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Relations\ContactController;
use App\Http\Controllers\Relations\RelationClassificationController;
use App\Http\Controllers\Relations\RelationCreateController;
use App\Http\Controllers\Relations\RelationEditController;
use App\Http\Controllers\Relations\RelationIndexController;
use App\Http\Controllers\Relations\RelationShowController;
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

Route::get('/relations/create', [RelationCreateController::class, 'create'])
    ->middleware([
        'auth',
        'domain.active',
        'administration.active',
        EnsureRelationsPermission::using(RelationsPermission::Create),
    ])
    ->name('relations.create');

Route::post('/relations', [RelationCreateController::class, 'store'])
    ->middleware([
        'auth',
        'domain.active',
        'administration.active',
        EnsureRelationsPermission::using(RelationsPermission::Create),
    ])
    ->name('relations.store');

Route::get('/relations/{relation}', RelationShowController::class)
    ->middleware([
        'auth',
        'domain.active',
        'administration.active',
        EnsureRelationsPermission::using(RelationsPermission::View),
    ])
    ->name('relations.show');

Route::get('/relations/{relation}/edit', [RelationEditController::class, 'edit'])
    ->middleware([
        'auth',
        'domain.active',
        'administration.active',
        EnsureRelationsPermission::using(RelationsPermission::Update),
    ])
    ->name('relations.edit');

Route::put('/relations/{relation}', [RelationEditController::class, 'update'])
    ->middleware([
        'auth',
        'domain.active',
        'administration.active',
        EnsureRelationsPermission::using(RelationsPermission::Update),
    ])
    ->name('relations.update');

Route::get('/relations/{relation}/contacts/create', [ContactController::class, 'create'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureRelationsPermission::using(RelationsPermission::Update)])
    ->name('relations.contacts.create');
Route::post('/relations/{relation}/contacts', [ContactController::class, 'store'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureRelationsPermission::using(RelationsPermission::Update)])
    ->name('relations.contacts.store');
Route::get('/relations/{relation}/contacts/{contact}', [ContactController::class, 'show'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureRelationsPermission::using(RelationsPermission::View)])
    ->name('relations.contacts.show');
Route::get('/relations/{relation}/contacts/{contact}/edit', [ContactController::class, 'edit'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureRelationsPermission::using(RelationsPermission::Update)])
    ->name('relations.contacts.edit');
Route::put('/relations/{relation}/contacts/{contact}', [ContactController::class, 'update'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureRelationsPermission::using(RelationsPermission::Update)])
    ->name('relations.contacts.update');
Route::post('/relations/{relation}/contacts/{contact}/activate', [ContactController::class, 'activate'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureRelationsPermission::using(RelationsPermission::Update)])
    ->name('relations.contacts.activate');
Route::delete('/relations/{relation}/contacts/{contact}', [ContactController::class, 'deactivate'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureRelationsPermission::using(RelationsPermission::Update)])
    ->name('relations.contacts.deactivate');

Route::post('/relations/{relation}/customer', [RelationClassificationController::class, 'storeCustomer'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureRelationsPermission::using(RelationsPermission::ClassifyCustomer)])
    ->name('relations.customer.store');
Route::delete('/relations/{relation}/customer', [RelationClassificationController::class, 'destroyCustomer'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureRelationsPermission::using(RelationsPermission::ClassifyCustomer)])
    ->name('relations.customer.destroy');
Route::post('/relations/{relation}/supplier', [RelationClassificationController::class, 'storeSupplier'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureRelationsPermission::using(RelationsPermission::ClassifySupplier)])
    ->name('relations.supplier.store');
Route::delete('/relations/{relation}/supplier', [RelationClassificationController::class, 'destroySupplier'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureRelationsPermission::using(RelationsPermission::ClassifySupplier)])
    ->name('relations.supplier.destroy');
