<?php

use App\Domain\Identity\Definitions\RelationsPermission;
use App\Domain\Identity\Definitions\SalesPermission;
use App\Http\Controllers\AdministrationSelectionController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Relations\AddressController;
use App\Http\Controllers\Relations\BankAccountController;
use App\Http\Controllers\Relations\ContactController;
use App\Http\Controllers\Relations\RelationClassificationController;
use App\Http\Controllers\Relations\RelationCreateController;
use App\Http\Controllers\Relations\RelationEditController;
use App\Http\Controllers\Relations\RelationIndexController;
use App\Http\Controllers\Relations\RelationShowController;
use App\Http\Controllers\Sales\QuotationController;
use App\Http\Controllers\Sales\QuotationLifecycleController;
use App\Http\Controllers\Sales\QuotationLineController;
use App\Http\Middleware\EnsureRelationsPermission;
use App\Http\Middleware\EnsureSalesPermission;
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

Route::get('/relations/{relation}/addresses/create', [AddressController::class, 'create'])->middleware(['auth', 'domain.active', 'administration.active', EnsureRelationsPermission::using(RelationsPermission::Update)])->name('relations.addresses.create');
Route::post('/relations/{relation}/addresses', [AddressController::class, 'store'])->middleware(['auth', 'domain.active', 'administration.active', EnsureRelationsPermission::using(RelationsPermission::Update)])->name('relations.addresses.store');
Route::get('/relations/{relation}/addresses/{address}', [AddressController::class, 'show'])->middleware(['auth', 'domain.active', 'administration.active', EnsureRelationsPermission::using(RelationsPermission::View)])->name('relations.addresses.show');
Route::get('/relations/{relation}/addresses/{address}/edit', [AddressController::class, 'edit'])->middleware(['auth', 'domain.active', 'administration.active', EnsureRelationsPermission::using(RelationsPermission::Update)])->name('relations.addresses.edit');
Route::put('/relations/{relation}/addresses/{address}', [AddressController::class, 'update'])->middleware(['auth', 'domain.active', 'administration.active', EnsureRelationsPermission::using(RelationsPermission::Update)])->name('relations.addresses.update');
Route::post('/relations/{relation}/addresses/{address}/activate', [AddressController::class, 'activate'])->middleware(['auth', 'domain.active', 'administration.active', EnsureRelationsPermission::using(RelationsPermission::Update)])->name('relations.addresses.activate');
Route::delete('/relations/{relation}/addresses/{address}', [AddressController::class, 'deactivate'])->middleware(['auth', 'domain.active', 'administration.active', EnsureRelationsPermission::using(RelationsPermission::Update)])->name('relations.addresses.deactivate');

Route::get('/relations/{relation}/bank-accounts/create', [BankAccountController::class, 'create'])->middleware(['auth', 'domain.active', 'administration.active', EnsureRelationsPermission::using(RelationsPermission::Update)])->name('relations.bank-accounts.create');
Route::post('/relations/{relation}/bank-accounts', [BankAccountController::class, 'store'])->middleware(['auth', 'domain.active', 'administration.active', EnsureRelationsPermission::using(RelationsPermission::Update)])->name('relations.bank-accounts.store');
Route::get('/relations/{relation}/bank-accounts/{bankAccount}', [BankAccountController::class, 'show'])->middleware(['auth', 'domain.active', 'administration.active', EnsureRelationsPermission::using(RelationsPermission::View)])->name('relations.bank-accounts.show');
Route::get('/relations/{relation}/bank-accounts/{bankAccount}/edit', [BankAccountController::class, 'edit'])->middleware(['auth', 'domain.active', 'administration.active', EnsureRelationsPermission::using(RelationsPermission::Update)])->name('relations.bank-accounts.edit');
Route::put('/relations/{relation}/bank-accounts/{bankAccount}', [BankAccountController::class, 'update'])->middleware(['auth', 'domain.active', 'administration.active', EnsureRelationsPermission::using(RelationsPermission::Update)])->name('relations.bank-accounts.update');
Route::post('/relations/{relation}/bank-accounts/{bankAccount}/activate', [BankAccountController::class, 'activate'])->middleware(['auth', 'domain.active', 'administration.active', EnsureRelationsPermission::using(RelationsPermission::Update)])->name('relations.bank-accounts.activate');
Route::delete('/relations/{relation}/bank-accounts/{bankAccount}', [BankAccountController::class, 'deactivate'])->middleware(['auth', 'domain.active', 'administration.active', EnsureRelationsPermission::using(RelationsPermission::Update)])->name('relations.bank-accounts.deactivate');

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

Route::get('/sales/quotations', [QuotationController::class, 'index'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::View)])
    ->name('sales.quotations.index');
Route::get('/sales/quotations/create', [QuotationController::class, 'create'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageQuotations)])
    ->name('sales.quotations.create');
Route::post('/sales/quotations', [QuotationController::class, 'store'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageQuotations)])
    ->name('sales.quotations.store');
Route::get('/sales/quotations/{quotation}', [QuotationController::class, 'show'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::View)])
    ->name('sales.quotations.show');
Route::get('/sales/quotations/{quotation}/edit', [QuotationController::class, 'edit'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageQuotations)])
    ->name('sales.quotations.edit');
Route::put('/sales/quotations/{quotation}', [QuotationController::class, 'update'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageQuotations)])
    ->name('sales.quotations.update');
Route::post('/sales/quotations/{quotation}/lines', [QuotationLineController::class, 'store'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageQuotations)])
    ->name('sales.quotations.lines.store');
Route::put('/sales/quotations/{quotation}/lines/{line}', [QuotationLineController::class, 'update'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageQuotations)])
    ->name('sales.quotations.lines.update');
Route::delete('/sales/quotations/{quotation}/lines/{line}', [QuotationLineController::class, 'destroy'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageQuotations)])
    ->name('sales.quotations.lines.destroy');
Route::post('/sales/quotations/{quotation}/send', [QuotationLifecycleController::class, 'send'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageQuotations)])
    ->name('sales.quotations.send');
Route::post('/sales/quotations/{quotation}/accept', [QuotationLifecycleController::class, 'accept'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageQuotations)])
    ->name('sales.quotations.accept');
Route::post('/sales/quotations/{quotation}/reject', [QuotationLifecycleController::class, 'reject'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageQuotations)])
    ->name('sales.quotations.reject');
Route::post('/sales/quotations/{quotation}/expire', [QuotationLifecycleController::class, 'expire'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageQuotations)])
    ->name('sales.quotations.expire');
