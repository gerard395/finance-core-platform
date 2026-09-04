<?php

use App\Domain\Identity\Definitions\AccountingPeriodPermission;
use App\Domain\Identity\Definitions\AdministrationPermission;
use App\Domain\Identity\Definitions\BankingPermission;
use App\Domain\Identity\Definitions\DeliveryOperationsPermission;
use App\Domain\Identity\Definitions\PurchasingPermission;
use App\Domain\Identity\Definitions\RelationsPermission;
use App\Domain\Identity\Definitions\SalesPermission;
use App\Http\Controllers\AccountingMasterDataController;
use App\Http\Controllers\AccountingPeriodController;
use App\Http\Controllers\AdministrationSelectionController;
use App\Http\Controllers\AdministrationSettingsController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Banking\BankImportController;
use App\Http\Controllers\Banking\BankPaymentController;
use App\Http\Controllers\Banking\BankPaymentLifecycleController;
use App\Http\Controllers\Banking\BankPaymentPostingController;
use App\Http\Controllers\Banking\BankPaymentReversalController;
use App\Http\Controllers\Banking\BankReconciliationController;
use App\Http\Controllers\Banking\ImportedBankEntryReversalController;
use App\Http\Controllers\BankingSettingsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Purchasing\PurchaseCreditController;
use App\Http\Controllers\Purchasing\PurchaseCreditLifecycleController;
use App\Http\Controllers\Purchasing\PurchaseCreditPostingController;
use App\Http\Controllers\Purchasing\PurchaseInvoiceController;
use App\Http\Controllers\Purchasing\PurchaseInvoiceLifecycleController;
use App\Http\Controllers\Purchasing\PurchaseInvoicePostingController;
use App\Http\Controllers\Relations\AddressController;
use App\Http\Controllers\Relations\BankAccountController;
use App\Http\Controllers\Relations\ContactController;
use App\Http\Controllers\Relations\RelationClassificationController;
use App\Http\Controllers\Relations\RelationCreateController;
use App\Http\Controllers\Relations\RelationEditController;
use App\Http\Controllers\Relations\RelationIndexController;
use App\Http\Controllers\Relations\RelationShowController;
use App\Http\Controllers\Relations\SalesDocumentRecipientPreferenceController;
use App\Http\Controllers\Sales\DeliveryOutcomeResolutionController;
use App\Http\Controllers\Sales\OrderController;
use App\Http\Controllers\Sales\OrderLifecycleController;
use App\Http\Controllers\Sales\OrderLineController;
use App\Http\Controllers\Sales\OrderSalesInvoiceController;
use App\Http\Controllers\Sales\QuotationController;
use App\Http\Controllers\Sales\QuotationLifecycleController;
use App\Http\Controllers\Sales\QuotationLineController;
use App\Http\Controllers\Sales\QuotationOrderController;
use App\Http\Controllers\Sales\SalesCreditInvoiceController;
use App\Http\Controllers\Sales\SalesCreditInvoiceLifecycleController;
use App\Http\Controllers\Sales\SalesCreditInvoicePostingController;
use App\Http\Controllers\Sales\SalesDocumentDeliveryController;
use App\Http\Controllers\Sales\SalesInvoiceController;
use App\Http\Controllers\Sales\SalesInvoiceLifecycleController;
use App\Http\Controllers\Sales\SalesInvoiceLineController;
use App\Http\Controllers\Sales\SalesInvoicePostingController;
use App\Http\Middleware\EnsureAccountingPeriodPermission;
use App\Http\Middleware\EnsureAdministrationPermission;
use App\Http\Middleware\EnsureBankingPermission;
use App\Http\Middleware\EnsureDeliveryOperationsPermission;
use App\Http\Middleware\EnsurePurchasingPermission;
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

Route::get('/purchasing/invoices', [PurchaseInvoiceController::class, 'index'])->middleware(['auth', 'domain.active', 'administration.active', EnsurePurchasingPermission::using(PurchasingPermission::View)])->name('purchasing.invoices.index');
Route::get('/purchasing/invoices/create', [PurchaseInvoiceController::class, 'create'])->middleware(['auth', 'domain.active', 'administration.active', EnsurePurchasingPermission::using(PurchasingPermission::ManageInvoiceDrafts)])->name('purchasing.invoices.create');
Route::post('/purchasing/invoices', [PurchaseInvoiceController::class, 'store'])->middleware(['auth', 'domain.active', 'administration.active', EnsurePurchasingPermission::using(PurchasingPermission::ManageInvoiceDrafts)])->name('purchasing.invoices.store');
Route::get('/purchasing/invoices/{invoice}', [PurchaseInvoiceController::class, 'show'])->middleware(['auth', 'domain.active', 'administration.active', EnsurePurchasingPermission::using(PurchasingPermission::View)])->name('purchasing.invoices.show');
Route::get('/purchasing/invoices/{invoice}/edit', [PurchaseInvoiceController::class, 'edit'])->middleware(['auth', 'domain.active', 'administration.active', EnsurePurchasingPermission::using(PurchasingPermission::ManageInvoiceDrafts)])->name('purchasing.invoices.edit');
Route::put('/purchasing/invoices/{invoice}', [PurchaseInvoiceController::class, 'update'])->middleware(['auth', 'domain.active', 'administration.active', EnsurePurchasingPermission::using(PurchasingPermission::ManageInvoiceDrafts)])->name('purchasing.invoices.update');
Route::post('/purchasing/invoices/{invoice}/cancel', [PurchaseInvoiceLifecycleController::class, 'cancel'])->middleware(['auth', 'domain.active', 'administration.active', EnsurePurchasingPermission::using(PurchasingPermission::ManageInvoiceDrafts)])->name('purchasing.invoices.cancel');
Route::post('/purchasing/invoices/{invoice}/finalize', [PurchaseInvoiceLifecycleController::class, 'finalize'])->middleware(['auth', 'domain.active', 'administration.active', EnsurePurchasingPermission::using(PurchasingPermission::FinalizeInvoices)])->name('purchasing.invoices.finalize');
Route::post('/purchasing/invoices/{invoice}/post', PurchaseInvoicePostingController::class)->middleware(['auth', 'domain.active', 'administration.active', EnsurePurchasingPermission::using(PurchasingPermission::PostInvoices)])->name('purchasing.invoices.post');

Route::get('/purchasing/credits', [PurchaseCreditController::class, 'index'])->middleware(['auth', 'domain.active', 'administration.active', EnsurePurchasingPermission::using(PurchasingPermission::View)])->name('purchasing.credits.index');
Route::get('/purchasing/credits/create', [PurchaseCreditController::class, 'create'])->middleware(['auth', 'domain.active', 'administration.active', EnsurePurchasingPermission::using(PurchasingPermission::ManageCreditDrafts)])->name('purchasing.credits.create');
Route::post('/purchasing/credits', [PurchaseCreditController::class, 'store'])->middleware(['auth', 'domain.active', 'administration.active', EnsurePurchasingPermission::using(PurchasingPermission::ManageCreditDrafts)])->name('purchasing.credits.store');
Route::get('/purchasing/credits/{credit}', [PurchaseCreditController::class, 'show'])->middleware(['auth', 'domain.active', 'administration.active', EnsurePurchasingPermission::using(PurchasingPermission::View)])->name('purchasing.credits.show');
Route::get('/purchasing/credits/{credit}/edit', [PurchaseCreditController::class, 'edit'])->middleware(['auth', 'domain.active', 'administration.active', EnsurePurchasingPermission::using(PurchasingPermission::ManageCreditDrafts)])->name('purchasing.credits.edit');
Route::put('/purchasing/credits/{credit}', [PurchaseCreditController::class, 'update'])->middleware(['auth', 'domain.active', 'administration.active', EnsurePurchasingPermission::using(PurchasingPermission::ManageCreditDrafts)])->name('purchasing.credits.update');
Route::post('/purchasing/credits/{credit}/cancel', [PurchaseCreditLifecycleController::class, 'cancel'])->middleware(['auth', 'domain.active', 'administration.active', EnsurePurchasingPermission::using(PurchasingPermission::ManageCreditDrafts)])->name('purchasing.credits.cancel');
Route::post('/purchasing/credits/{credit}/finalize', [PurchaseCreditLifecycleController::class, 'finalize'])->middleware(['auth', 'domain.active', 'administration.active', EnsurePurchasingPermission::using(PurchasingPermission::FinalizeCredits)])->name('purchasing.credits.finalize');
Route::post('/purchasing/credits/{credit}/post', PurchaseCreditPostingController::class)->middleware(['auth', 'domain.active', 'administration.active', EnsurePurchasingPermission::using(PurchasingPermission::PostCredits)])->name('purchasing.credits.post');

Route::get('/banking/payments', [BankPaymentController::class, 'index'])->middleware(['auth', 'domain.active', 'administration.active', EnsureBankingPermission::using(BankingPermission::View)])->name('banking.payments.index');
Route::get('/banking/payments/create', [BankPaymentController::class, 'create'])->middleware(['auth', 'domain.active', 'administration.active', EnsureBankingPermission::using(BankingPermission::ManagePayments)])->name('banking.payments.create');
Route::post('/banking/payments', [BankPaymentController::class, 'store'])->middleware(['auth', 'domain.active', 'administration.active', EnsureBankingPermission::using(BankingPermission::ManagePayments)])->name('banking.payments.store');
Route::get('/banking/payments/{payment}', [BankPaymentController::class, 'show'])->middleware(['auth', 'domain.active', 'administration.active', EnsureBankingPermission::using(BankingPermission::View)])->name('banking.payments.show');
Route::get('/banking/payments/{payment}/edit', [BankPaymentController::class, 'edit'])->middleware(['auth', 'domain.active', 'administration.active', EnsureBankingPermission::using(BankingPermission::ManagePayments)])->name('banking.payments.edit');
Route::put('/banking/payments/{payment}', [BankPaymentController::class, 'update'])->middleware(['auth', 'domain.active', 'administration.active', EnsureBankingPermission::using(BankingPermission::ManagePayments)])->name('banking.payments.update');
Route::post('/banking/payments/{payment}/cancel', [BankPaymentLifecycleController::class, 'cancel'])->middleware(['auth', 'domain.active', 'administration.active', EnsureBankingPermission::using(BankingPermission::ManagePayments)])->name('banking.payments.cancel');
Route::post('/banking/payments/{payment}/finalize', [BankPaymentLifecycleController::class, 'finalize'])->middleware(['auth', 'domain.active', 'administration.active', EnsureBankingPermission::using(BankingPermission::ManagePayments)])->name('banking.payments.finalize');
Route::post('/banking/payments/{payment}/post', BankPaymentPostingController::class)->middleware(['auth', 'domain.active', 'administration.active', EnsureBankingPermission::using(BankingPermission::PostPayments)])->name('banking.payments.post');
Route::get('/banking/payments/{payment}/reverse', [BankPaymentReversalController::class, 'create'])->middleware(['auth', 'domain.active', 'administration.active', EnsureBankingPermission::using(BankingPermission::View), EnsureBankingPermission::using(BankingPermission::ReversePayments)])->name('banking.payments.reverse.create');
Route::post('/banking/payments/{payment}/reverse', [BankPaymentReversalController::class, 'store'])->middleware(['auth', 'domain.active', 'administration.active', EnsureBankingPermission::using(BankingPermission::ReversePayments)])->name('banking.payments.reverse.store');

Route::get('/bank/import', [BankImportController::class, 'create'])->middleware(['auth', 'domain.active', 'administration.active', EnsureBankingPermission::using(BankingPermission::ImportUpload)])->name('banking.import.create');
Route::post('/bank/import/preview', [BankImportController::class, 'preview'])->middleware(['auth', 'domain.active', 'administration.active', EnsureBankingPermission::using(BankingPermission::ImportUpload)])->name('banking.import.preview');
Route::post('/bank/import/confirm', [BankImportController::class, 'confirm'])->middleware(['auth', 'domain.active', 'administration.active', EnsureBankingPermission::using(BankingPermission::ImportUpload)])->name('banking.import.confirm');
Route::get('/bank/import/batches', [BankImportController::class, 'batches'])->middleware(['auth', 'domain.active', 'administration.active', EnsureBankingPermission::using(BankingPermission::View)])->name('banking.import.batches.index');
Route::get('/bank/import/batches/{batch}', [BankImportController::class, 'batch'])->middleware(['auth', 'domain.active', 'administration.active', EnsureBankingPermission::using(BankingPermission::View)])->name('banking.import.batches.show');
Route::get('/bank/import/statements/{statement}', [BankImportController::class, 'statement'])->middleware(['auth', 'domain.active', 'administration.active', EnsureBankingPermission::using(BankingPermission::View)])->name('banking.import.statements.show');
Route::get('/bank/reconciliation', [BankReconciliationController::class, 'index'])->middleware(['auth', 'domain.active', 'administration.active', EnsureBankingPermission::using(BankingPermission::View)])->name('banking.reconciliation.index');
Route::get('/bank/reconciliation/{entry}', [BankReconciliationController::class, 'show'])->middleware(['auth', 'domain.active', 'administration.active', EnsureBankingPermission::using(BankingPermission::View)])->name('banking.reconciliation.show');
Route::post('/bank/reconciliation/{entry}/ignore', [BankReconciliationController::class, 'ignore'])->middleware(['auth', 'domain.active', 'administration.active', EnsureBankingPermission::using(BankingPermission::Reconcile)])->name('banking.reconciliation.ignore');
Route::post('/bank/reconciliation/{entry}/restore', [BankReconciliationController::class, 'restore'])->middleware(['auth', 'domain.active', 'administration.active', EnsureBankingPermission::using(BankingPermission::Reconcile)])->name('banking.reconciliation.restore');
Route::post('/bank/reconciliation/{entry}/post', [BankReconciliationController::class, 'post'])->middleware(['auth', 'domain.active', 'administration.active', EnsureBankingPermission::using(BankingPermission::Reconcile), EnsureBankingPermission::using(BankingPermission::ImportPost)])->name('banking.reconciliation.post');
Route::get('/bank/reconciliation/{entry}/reverse', [ImportedBankEntryReversalController::class, 'create'])->middleware(['auth', 'domain.active', 'administration.active', EnsureBankingPermission::using(BankingPermission::View), EnsureBankingPermission::using(BankingPermission::ReversePayments)])->name('banking.reconciliation.reverse.create');
Route::post('/bank/reconciliation/{entry}/reverse', [ImportedBankEntryReversalController::class, 'store'])->middleware(['auth', 'domain.active', 'administration.active', EnsureBankingPermission::using(BankingPermission::ReversePayments)])->name('banking.reconciliation.reverse.store');

Route::get('/settings/administration', [AdministrationSettingsController::class, 'edit'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureAdministrationPermission::using(AdministrationPermission::UpdateSettings)])
    ->name('settings.administration.edit');
Route::put('/settings/administration', [AdministrationSettingsController::class, 'update'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureAdministrationPermission::using(AdministrationPermission::UpdateSettings)])
    ->name('settings.administration.update');
Route::put('/settings/administration/sales-posting', [AdministrationSettingsController::class, 'updateSalesPosting'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureAdministrationPermission::using(AdministrationPermission::UpdateSettings)])
    ->name('settings.administration.sales-posting.update');
Route::put('/settings/administration/purchase-posting', [AdministrationSettingsController::class, 'updatePurchasePosting'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureAdministrationPermission::using(AdministrationPermission::UpdateSettings)])
    ->name('settings.administration.purchase-posting.update');
Route::post('/settings/administration/purchase-tax-codes', [AdministrationSettingsController::class, 'provisionPurchaseTaxCodes'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureAdministrationPermission::using(AdministrationPermission::UpdateSettings)])
    ->name('settings.administration.purchase-tax-codes.provision');
Route::put('/settings/administration/document-delivery', [AdministrationSettingsController::class, 'updateDocumentSettings'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureAdministrationPermission::using(AdministrationPermission::UpdateSettings)])
    ->name('settings.administration.document-delivery.update');
Route::post('/settings/administration/bank-accounts', [BankingSettingsController::class, 'store'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureAdministrationPermission::using(AdministrationPermission::UpdateSettings)])
    ->name('settings.administration.bank-accounts.store');
Route::put('/settings/administration/bank-accounts/{bankAccount}', [BankingSettingsController::class, 'update'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureAdministrationPermission::using(AdministrationPermission::UpdateSettings)])
    ->name('settings.administration.bank-accounts.update');
Route::post('/settings/administration/bank-accounts/{bankAccount}/activate', [BankingSettingsController::class, 'activate'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureAdministrationPermission::using(AdministrationPermission::UpdateSettings)])
    ->name('settings.administration.bank-accounts.activate');
Route::post('/settings/administration/bank-accounts/{bankAccount}/deactivate', [BankingSettingsController::class, 'deactivate'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureAdministrationPermission::using(AdministrationPermission::UpdateSettings)])
    ->name('settings.administration.bank-accounts.deactivate');
Route::put('/settings/administration/bank-accounts/{bankAccount}/configuration', [BankingSettingsController::class, 'configure'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureAdministrationPermission::using(AdministrationPermission::UpdateSettings)])
    ->name('settings.administration.bank-accounts.configuration.update');

Route::middleware(['auth', 'domain.active', 'administration.active', EnsureAdministrationPermission::using(AdministrationPermission::UpdateSettings)])->group(function (): void {
    Route::get('/settings/journals', [AccountingMasterDataController::class, 'journals'])->name('settings.journals.index');
    Route::get('/settings/journals/create', [AccountingMasterDataController::class, 'createJournal'])->name('settings.journals.create');
    Route::post('/settings/journals', [AccountingMasterDataController::class, 'storeJournal'])->name('settings.journals.store');
    Route::get('/settings/journals/{journal}/edit', [AccountingMasterDataController::class, 'editJournal'])->name('settings.journals.edit');
    Route::put('/settings/journals/{journal}', [AccountingMasterDataController::class, 'updateJournal'])->name('settings.journals.update');
    Route::post('/settings/journals/{journal}/activate', [AccountingMasterDataController::class, 'activateJournal'])->name('settings.journals.activate');
    Route::post('/settings/journals/{journal}/deactivate', [AccountingMasterDataController::class, 'deactivateJournal'])->name('settings.journals.deactivate');
    Route::get('/settings/ledger-accounts', [AccountingMasterDataController::class, 'accounts'])->name('settings.ledger-accounts.index');
    Route::get('/settings/ledger-accounts/create', [AccountingMasterDataController::class, 'createAccount'])->name('settings.ledger-accounts.create');
    Route::post('/settings/ledger-accounts', [AccountingMasterDataController::class, 'storeAccount'])->name('settings.ledger-accounts.store');
    Route::get('/settings/ledger-accounts/{account}/edit', [AccountingMasterDataController::class, 'editAccount'])->name('settings.ledger-accounts.edit');
    Route::put('/settings/ledger-accounts/{account}', [AccountingMasterDataController::class, 'updateAccount'])->name('settings.ledger-accounts.update');
    Route::post('/settings/ledger-accounts/{account}/activate', [AccountingMasterDataController::class, 'activateAccount'])->name('settings.ledger-accounts.activate');
    Route::post('/settings/ledger-accounts/{account}/deactivate', [AccountingMasterDataController::class, 'deactivateAccount'])->name('settings.ledger-accounts.deactivate');
});

Route::get('/settings/accounting-periods', [AccountingPeriodController::class, 'index'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureAccountingPeriodPermission::using(AccountingPeriodPermission::View)])
    ->name('settings.accounting-periods.index');
Route::get('/settings/accounting-periods/create', [AccountingPeriodController::class, 'create'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureAccountingPeriodPermission::using(AccountingPeriodPermission::Manage)])
    ->name('settings.accounting-periods.create');
Route::post('/settings/accounting-periods', [AccountingPeriodController::class, 'store'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureAccountingPeriodPermission::using(AccountingPeriodPermission::Manage)])
    ->name('settings.accounting-periods.store');
Route::get('/settings/accounting-periods/{bookYear}', [AccountingPeriodController::class, 'show'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureAccountingPeriodPermission::using(AccountingPeriodPermission::View)])
    ->name('settings.accounting-periods.show');
Route::get('/settings/accounting-periods/{bookYear}/edit', [AccountingPeriodController::class, 'edit'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureAccountingPeriodPermission::using(AccountingPeriodPermission::Manage)])
    ->name('settings.accounting-periods.edit');
Route::put('/settings/accounting-periods/{bookYear}', [AccountingPeriodController::class, 'update'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureAccountingPeriodPermission::using(AccountingPeriodPermission::Manage)])
    ->name('settings.accounting-periods.update');
Route::post('/settings/accounting-periods/{bookYear}/periods', [AccountingPeriodController::class, 'storePeriod'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureAccountingPeriodPermission::using(AccountingPeriodPermission::Manage)])
    ->name('settings.accounting-periods.periods.store');
Route::post('/settings/accounting-periods/{bookYear}/periods/replace-with-months', [AccountingPeriodController::class, 'replacePlan'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureAccountingPeriodPermission::using(AccountingPeriodPermission::Manage)])
    ->name('settings.accounting-periods.periods.replace');
Route::post('/settings/accounting-periods/{bookYear}/periods/{period}/close', [AccountingPeriodController::class, 'close'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureAccountingPeriodPermission::using(AccountingPeriodPermission::Close)])
    ->name('settings.accounting-periods.periods.close');
Route::post('/settings/accounting-periods/{bookYear}/periods/{period}/reopen', [AccountingPeriodController::class, 'reopen'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureAccountingPeriodPermission::using(AccountingPeriodPermission::Reopen)])
    ->name('settings.accounting-periods.periods.reopen');

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
Route::post('/relations/{relation}/document-recipients', [SalesDocumentRecipientPreferenceController::class, 'store'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureRelationsPermission::using(RelationsPermission::Update)])
    ->name('relations.document-recipients.store');
Route::delete('/relations/{relation}/document-recipients/{purpose}', [SalesDocumentRecipientPreferenceController::class, 'destroy'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureRelationsPermission::using(RelationsPermission::Update)])
    ->name('relations.document-recipients.destroy');

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
Route::post('/sales/quotations/{quotation}/delivery', [SalesDocumentDeliveryController::class, 'quotation'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageQuotations)])
    ->name('sales.quotations.delivery.store');
Route::post('/sales/quotations/{quotation}/delivery/resend', [SalesDocumentDeliveryController::class, 'resendQuotation'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageQuotations)])
    ->name('sales.quotations.delivery.resend');
Route::get('/sales/quotations/{quotation}/document', [SalesDocumentDeliveryController::class, 'downloadQuotation'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::View)])
    ->name('sales.quotations.document.download');
Route::post('/sales/quotations/{quotation}/accept', [QuotationLifecycleController::class, 'accept'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageQuotations)])
    ->name('sales.quotations.accept');
Route::post('/sales/quotations/{quotation}/reject', [QuotationLifecycleController::class, 'reject'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageQuotations)])
    ->name('sales.quotations.reject');
Route::post('/sales/quotations/{quotation}/expire', [QuotationLifecycleController::class, 'expire'])
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageQuotations)])
    ->name('sales.quotations.expire');
Route::post('/sales/quotations/{quotation}/order', QuotationOrderController::class)
    ->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageOrders)])
    ->name('sales.quotations.order.store');

Route::get('/sales/orders', [OrderController::class, 'index'])->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::View)])->name('sales.orders.index');
Route::get('/sales/orders/create', [OrderController::class, 'create'])->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageOrders)])->name('sales.orders.create');
Route::post('/sales/orders', [OrderController::class, 'store'])->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageOrders)])->name('sales.orders.store');
Route::get('/sales/orders/{order}', [OrderController::class, 'show'])->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::View)])->name('sales.orders.show');
Route::get('/sales/orders/{order}/edit', [OrderController::class, 'edit'])->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageOrders)])->name('sales.orders.edit');
Route::put('/sales/orders/{order}', [OrderController::class, 'update'])->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageOrders)])->name('sales.orders.update');
Route::post('/sales/orders/{order}/lines', [OrderLineController::class, 'store'])->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageOrders)])->name('sales.orders.lines.store');
Route::put('/sales/orders/{order}/lines/{line}', [OrderLineController::class, 'update'])->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageOrders)])->name('sales.orders.lines.update');
Route::delete('/sales/orders/{order}/lines/{line}', [OrderLineController::class, 'destroy'])->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageOrders)])->name('sales.orders.lines.destroy');
Route::post('/sales/orders/{order}/confirm', [OrderLifecycleController::class, 'confirm'])->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageOrders)])->name('sales.orders.confirm');
Route::post('/sales/orders/{order}/cancel', [OrderLifecycleController::class, 'cancel'])->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageOrders)])->name('sales.orders.cancel');
Route::get('/sales/orders/{order}/invoice/create', [OrderSalesInvoiceController::class, 'create'])->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageInvoiceDrafts)])->name('sales.orders.invoice.create');
Route::post('/sales/orders/{order}/invoice', [OrderSalesInvoiceController::class, 'store'])->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageInvoiceDrafts)])->name('sales.orders.invoice.store');

Route::get('/sales/invoices', [SalesInvoiceController::class, 'index'])->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::View)])->name('sales.invoices.index');
Route::get('/sales/invoices/create', [SalesInvoiceController::class, 'create'])->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageInvoiceDrafts)])->name('sales.invoices.create');
Route::post('/sales/invoices', [SalesInvoiceController::class, 'store'])->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageInvoiceDrafts)])->name('sales.invoices.store');
Route::get('/sales/invoices/{invoice}', [SalesInvoiceController::class, 'show'])->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::View)])->name('sales.invoices.show');
Route::get('/sales/invoices/{invoice}/edit', [SalesInvoiceController::class, 'edit'])->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageInvoiceDrafts)])->name('sales.invoices.edit');
Route::put('/sales/invoices/{invoice}', [SalesInvoiceController::class, 'update'])->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageInvoiceDrafts)])->name('sales.invoices.update');
Route::post('/sales/invoices/{invoice}/lines', [SalesInvoiceLineController::class, 'store'])->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageInvoiceDrafts)])->name('sales.invoices.lines.store');
Route::put('/sales/invoices/{invoice}/lines/{line}', [SalesInvoiceLineController::class, 'update'])->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageInvoiceDrafts)])->name('sales.invoices.lines.update');
Route::delete('/sales/invoices/{invoice}/lines/{line}', [SalesInvoiceLineController::class, 'destroy'])->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageInvoiceDrafts)])->name('sales.invoices.lines.destroy');
Route::post('/sales/invoices/{invoice}/finalize', [SalesInvoiceLifecycleController::class, 'finalize'])->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::IssueInvoices)])->name('sales.invoices.finalize');
Route::post('/sales/invoices/{invoice}/cancel', [SalesInvoiceLifecycleController::class, 'cancel'])->middleware(['auth', 'domain.active', 'administration.active'])->name('sales.invoices.cancel');
Route::post('/sales/invoices/{invoice}/post', SalesInvoicePostingController::class)->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::PostInvoices)])->name('sales.invoices.post');
Route::post('/sales/invoices/{invoice}/delivery', [SalesDocumentDeliveryController::class, 'invoice'])->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::IssueInvoices)])->name('sales.invoices.delivery.store');
Route::post('/sales/invoices/{invoice}/delivery/resend', [SalesDocumentDeliveryController::class, 'resendInvoice'])->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::IssueInvoices)])->name('sales.invoices.delivery.resend');
Route::get('/sales/invoices/{invoice}/document', [SalesDocumentDeliveryController::class, 'downloadInvoice'])->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::View)])->name('sales.invoices.document.download');
Route::get('/sales/credit-invoices', [SalesCreditInvoiceController::class, 'index'])->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::View)])->name('sales.credit-invoices.index');
Route::get('/sales/credit-invoices/create', [SalesCreditInvoiceController::class, 'create'])->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageCreditInvoiceDrafts)])->name('sales.credit-invoices.create');
Route::post('/sales/credit-invoices', [SalesCreditInvoiceController::class, 'store'])->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::ManageCreditInvoiceDrafts)])->name('sales.credit-invoices.store');
Route::get('/sales/credit-invoices/{creditInvoice}', [SalesCreditInvoiceController::class, 'show'])->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::View)])->name('sales.credit-invoices.show');
Route::post('/sales/credit-invoices/{creditInvoice}/finalize', [SalesCreditInvoiceLifecycleController::class, 'finalize'])->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::IssueCreditInvoices)])->name('sales.credit-invoices.finalize');
Route::post('/sales/credit-invoices/{creditInvoice}/cancel', [SalesCreditInvoiceLifecycleController::class, 'cancel'])->middleware(['auth', 'domain.active', 'administration.active'])->name('sales.credit-invoices.cancel');
Route::post('/sales/credit-invoices/{creditInvoice}/post', SalesCreditInvoicePostingController::class)->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::PostCreditInvoices)])->name('sales.credit-invoices.post');
Route::post('/sales/credit-invoices/{creditInvoice}/delivery', [SalesDocumentDeliveryController::class, 'credit'])->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::IssueCreditInvoices)])->name('sales.credit-invoices.delivery.store');
Route::post('/sales/credit-invoices/{creditInvoice}/delivery/resend', [SalesDocumentDeliveryController::class, 'resendCredit'])->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::IssueCreditInvoices)])->name('sales.credit-invoices.delivery.resend');
Route::get('/sales/credit-invoices/{creditInvoice}/document', [SalesDocumentDeliveryController::class, 'downloadCredit'])->middleware(['auth', 'domain.active', 'administration.active', EnsureSalesPermission::using(SalesPermission::View)])->name('sales.credit-invoices.document.download');
Route::post('/sales/delivery/outcomes/resolve', DeliveryOutcomeResolutionController::class)->middleware(['auth', 'domain.active', 'administration.active', EnsureDeliveryOperationsPermission::using(DeliveryOperationsPermission::ResolveUnknownOutcome)])->name('sales.delivery.outcomes.resolve');
