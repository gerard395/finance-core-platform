<?php

namespace App\Providers;

use App\Application\Accounting\JournalEntryReadRepository;
use App\Application\Accounting\JournalEntryStore;
use App\Application\Accounting\JournalReadRepository;
use App\Application\Accounting\JournalStore;
use App\Application\Accounting\LedgerAccountReadRepository;
use App\Application\Accounting\LedgerAccountStore;
use App\Application\Accounting\OpenItemReadRepository;
use App\Application\Accounting\OpenItemSettlementStore;
use App\Application\Accounting\OpenItemStore;
use App\Application\Administration\AdministrationRepository;
use App\Application\Fiscal\TaxCodeReadRepository;
use App\Application\Fiscal\TaxCodeStore;
use App\Application\Fiscal\TaxPostingReadRepository;
use App\Application\Fiscal\TaxPostingStore;
use App\Application\Identity\AdministrationMembershipRepository;
use App\Application\Identity\AuthAccountStore;
use App\Application\Identity\AuthorizationReadRepository;
use App\Application\Identity\MembershipRoleRepository;
use App\Application\Identity\PasswordHasher;
use App\Application\Identity\PermissionRepository;
use App\Application\Identity\RolePermissionRepository;
use App\Application\Identity\RoleRepository;
use App\Application\Identity\UserRepository;
use App\Application\Relations\AddressReadRepository;
use App\Application\Relations\AddressWriter;
use App\Application\Relations\BankAccountReadRepository;
use App\Application\Relations\BankAccountWriter;
use App\Application\Relations\ContactReadRepository;
use App\Application\Relations\ContactWriter;
use App\Application\Relations\CustomerClassificationWriter;
use App\Application\Relations\CustomerReadRepository;
use App\Application\Relations\CustomerStore;
use App\Application\Relations\RelationClassificationIdentityGenerator;
use App\Application\Relations\RelationClassificationReader;
use App\Application\Relations\RelationCreator;
use App\Application\Relations\RelationListReadRepository;
use App\Application\Relations\RelationNumberAllocator;
use App\Application\Relations\RelationNumberSequenceProvisioner;
use App\Application\Relations\RelationReadRepository;
use App\Application\Relations\RelationStore;
use App\Application\Relations\RelationUpdater;
use App\Application\Relations\SupplierClassificationWriter;
use App\Application\Relations\SupplierReadRepository;
use App\Application\Relations\SupplierStore;
use App\Application\Sales\OrderCreator;
use App\Application\Sales\OrderDetailReadRepository;
use App\Application\Sales\OrderListReadRepository;
use App\Application\Sales\OrderReadRepository;
use App\Application\Sales\OrderUpdater;
use App\Application\Sales\QuotationCreator;
use App\Application\Sales\QuotationDetailReadRepository;
use App\Application\Sales\QuotationListReadRepository;
use App\Application\Sales\QuotationReadRepository;
use App\Application\Sales\QuotationUpdater;
use App\Application\Sales\SalesCustomerContextReader;
use App\Application\Sales\SalesInvoiceCreator;
use App\Application\Sales\SalesInvoiceDetailReadRepository;
use App\Application\Sales\SalesInvoiceListReadRepository;
use App\Application\Sales\SalesInvoiceReadRepository;
use App\Application\Sales\SalesInvoiceUpdater;
use App\Application\Sales\SalesNumberAllocator;
use App\Application\Sales\SalesNumberSequenceProvisioner;
use App\Application\Shared\TransactionManager;
use App\Infrastructure\Auth\EloquentAuthAccountStore;
use App\Infrastructure\Auth\LaravelPasswordHasher;
use App\Infrastructure\Persistence\Eloquent\EloquentAddressReadRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentAddressWriter;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationMembershipRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentAuthorizationReadRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentBankAccountReadRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentBankAccountWriter;
use App\Infrastructure\Persistence\Eloquent\EloquentContactReadRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentContactWriter;
use App\Infrastructure\Persistence\Eloquent\EloquentCustomerRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentJournalEntryRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentJournalRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentLedgerAccountRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentMembershipRoleRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentOpenItemRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentOrderReadRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentOrderRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentPermissionRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentQuotationReadRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentQuotationRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentRelationClassificationReader;
use App\Infrastructure\Persistence\Eloquent\EloquentRelationListReadRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentRelationRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentRolePermissionRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentRoleRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentSalesInvoiceReadRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentSalesInvoiceRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentSupplierRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentTaxCodeRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentTaxPostingRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentUserRepository;
use App\Infrastructure\Persistence\LaravelDatabaseTransactionManager;
use App\Infrastructure\Relations\DatabaseRelationNumberSequence;
use App\Infrastructure\Relations\LaravelRelationClassificationIdentityGenerator;
use App\Infrastructure\Sales\DatabaseSalesNumberSequence;
use App\Infrastructure\Sales\EloquentSalesCustomerContextReader;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepository::class, EloquentUserRepository::class);
        $this->app->bind(LedgerAccountReadRepository::class, EloquentLedgerAccountRepository::class);
        $this->app->bind(LedgerAccountStore::class, EloquentLedgerAccountRepository::class);
        $this->app->bind(JournalEntryReadRepository::class, EloquentJournalEntryRepository::class);
        $this->app->bind(JournalEntryStore::class, EloquentJournalEntryRepository::class);
        $this->app->bind(JournalReadRepository::class, EloquentJournalRepository::class);
        $this->app->bind(JournalStore::class, EloquentJournalRepository::class);
        $this->app->bind(OpenItemReadRepository::class, EloquentOpenItemRepository::class);
        $this->app->bind(OpenItemStore::class, EloquentOpenItemRepository::class);
        $this->app->bind(OpenItemSettlementStore::class, EloquentOpenItemRepository::class);
        $this->app->bind(TaxPostingReadRepository::class, EloquentTaxPostingRepository::class);
        $this->app->bind(TaxPostingStore::class, EloquentTaxPostingRepository::class);
        $this->app->bind(TaxCodeReadRepository::class, EloquentTaxCodeRepository::class);
        $this->app->bind(TaxCodeStore::class, EloquentTaxCodeRepository::class);
        $this->app->bind(RelationReadRepository::class, EloquentRelationRepository::class);
        $this->app->bind(RelationStore::class, EloquentRelationRepository::class);
        $this->app->bind(RelationCreator::class, EloquentRelationRepository::class);
        $this->app->bind(RelationUpdater::class, EloquentRelationRepository::class);
        $this->app->bind(ContactReadRepository::class, EloquentContactReadRepository::class);
        $this->app->bind(ContactWriter::class, EloquentContactWriter::class);
        $this->app->bind(AddressReadRepository::class, EloquentAddressReadRepository::class);
        $this->app->bind(AddressWriter::class, EloquentAddressWriter::class);
        $this->app->bind(BankAccountReadRepository::class, EloquentBankAccountReadRepository::class);
        $this->app->bind(BankAccountWriter::class, EloquentBankAccountWriter::class);
        $this->app->bind(CustomerReadRepository::class, EloquentCustomerRepository::class);
        $this->app->bind(CustomerClassificationWriter::class, EloquentCustomerRepository::class);
        $this->app->bind(CustomerStore::class, EloquentCustomerRepository::class);
        $this->app->bind(SupplierReadRepository::class, EloquentSupplierRepository::class);
        $this->app->bind(SupplierClassificationWriter::class, EloquentSupplierRepository::class);
        $this->app->bind(SupplierStore::class, EloquentSupplierRepository::class);
        $this->app->bind(RelationClassificationReader::class, EloquentRelationClassificationReader::class);
        $this->app->bind(RelationListReadRepository::class, EloquentRelationListReadRepository::class);
        $this->app->bind(RelationNumberAllocator::class, DatabaseRelationNumberSequence::class);
        $this->app->bind(RelationNumberSequenceProvisioner::class, DatabaseRelationNumberSequence::class);
        $this->app->bind(SalesNumberAllocator::class, DatabaseSalesNumberSequence::class);
        $this->app->bind(SalesNumberSequenceProvisioner::class, DatabaseSalesNumberSequence::class);
        $this->app->bind(SalesCustomerContextReader::class, EloquentSalesCustomerContextReader::class);
        $this->app->bind(QuotationReadRepository::class, EloquentQuotationRepository::class);
        $this->app->bind(QuotationCreator::class, EloquentQuotationRepository::class);
        $this->app->bind(QuotationUpdater::class, EloquentQuotationRepository::class);
        $this->app->bind(QuotationListReadRepository::class, EloquentQuotationReadRepository::class);
        $this->app->bind(QuotationDetailReadRepository::class, EloquentQuotationReadRepository::class);
        $this->app->bind(OrderReadRepository::class, EloquentOrderRepository::class);
        $this->app->bind(OrderCreator::class, EloquentOrderRepository::class);
        $this->app->bind(OrderUpdater::class, EloquentOrderRepository::class);
        $this->app->bind(OrderListReadRepository::class, EloquentOrderReadRepository::class);
        $this->app->bind(OrderDetailReadRepository::class, EloquentOrderReadRepository::class);
        $this->app->bind(SalesInvoiceReadRepository::class, EloquentSalesInvoiceRepository::class);
        $this->app->bind(SalesInvoiceCreator::class, EloquentSalesInvoiceRepository::class);
        $this->app->bind(SalesInvoiceUpdater::class, EloquentSalesInvoiceRepository::class);
        $this->app->bind(SalesInvoiceListReadRepository::class, EloquentSalesInvoiceReadRepository::class);
        $this->app->bind(SalesInvoiceDetailReadRepository::class, EloquentSalesInvoiceReadRepository::class);
        $this->app->bind(RelationClassificationIdentityGenerator::class, LaravelRelationClassificationIdentityGenerator::class);
        $this->app->bind(AdministrationRepository::class, EloquentAdministrationRepository::class);
        $this->app->bind(AdministrationMembershipRepository::class, EloquentAdministrationMembershipRepository::class);
        $this->app->bind(RoleRepository::class, EloquentRoleRepository::class);
        $this->app->bind(PermissionRepository::class, EloquentPermissionRepository::class);
        $this->app->bind(RolePermissionRepository::class, EloquentRolePermissionRepository::class);
        $this->app->bind(MembershipRoleRepository::class, EloquentMembershipRoleRepository::class);
        $this->app->bind(AuthorizationReadRepository::class, EloquentAuthorizationReadRepository::class);
        $this->app->bind(AuthAccountStore::class, EloquentAuthAccountStore::class);
        $this->app->bind(PasswordHasher::class, LaravelPasswordHasher::class);
        $this->app->bind(TransactionManager::class, LaravelDatabaseTransactionManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
