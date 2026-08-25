<?php

namespace App\Providers;

use App\Application\Accounting\AccountingMasterDataIdentityGenerator;
use App\Application\Accounting\JournalEntryReadRepository;
use App\Application\Accounting\JournalEntryStore;
use App\Application\Accounting\JournalReadRepository;
use App\Application\Accounting\JournalStore;
use App\Application\Accounting\LedgerAccountReadRepository;
use App\Application\Accounting\LedgerAccountStore;
use App\Application\Accounting\OpenItemMatchIdentityGenerator;
use App\Application\Accounting\OpenItemMatchRepository;
use App\Application\Accounting\OpenItemReadRepository;
use App\Application\Accounting\OpenItemSettlementStore;
use App\Application\Accounting\OpenItemStore;
use App\Application\Administration\AdministrationFiscalPartyReader;
use App\Application\Administration\AdministrationRepository;
use App\Application\Administration\AdministrationSettingsReader;
use App\Application\Administration\AdministrationSettingsUpdater;
use App\Application\Development\DevelopmentAccountingMasterDataProvisioner;
use App\Application\Fiscal\TaxCodeCatalogueProvisioner;
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
use App\Application\Relations\RelationFiscalPartyReader;
use App\Application\Relations\RelationListReadRepository;
use App\Application\Relations\RelationNumberAllocator;
use App\Application\Relations\RelationNumberSequenceProvisioner;
use App\Application\Relations\RelationReadRepository;
use App\Application\Relations\RelationStore;
use App\Application\Relations\RelationUpdater;
use App\Application\Relations\SupplierClassificationWriter;
use App\Application\Relations\SupplierReadRepository;
use App\Application\Relations\SupplierStore;
use App\Application\Sales\CreateSalesInvoicePostingRequest;
use App\Application\Sales\EligibleSalesCreditSourceReadRepository;
use App\Application\Sales\OrderBySourceQuotationRepository;
use App\Application\Sales\OrderCreator;
use App\Application\Sales\OrderDetailReadRepository;
use App\Application\Sales\OrderInvoiceDraftIdentityGenerator;
use App\Application\Sales\OrderInvoiceDraftRequestReader;
use App\Application\Sales\OrderInvoiceLifecycleIdentityGenerator;
use App\Application\Sales\OrderInvoicingFactStore;
use App\Application\Sales\OrderInvoicingOrderLocker;
use App\Application\Sales\OrderInvoicingProgressReader;
use App\Application\Sales\OrderInvoicingSource;
use App\Application\Sales\OrderListReadRepository;
use App\Application\Sales\OrderReadRepository;
use App\Application\Sales\OrderUpdater;
use App\Application\Sales\PostSalesCreditInvoiceWithTax;
use App\Application\Sales\PostSalesInvoiceWithTax;
use App\Application\Sales\QuotationCreator;
use App\Application\Sales\QuotationDetailReadRepository;
use App\Application\Sales\QuotationListReadRepository;
use App\Application\Sales\QuotationOrderConversionIdentityGenerator;
use App\Application\Sales\QuotationOrderConversionSource;
use App\Application\Sales\QuotationReadRepository;
use App\Application\Sales\QuotationUpdater;
use App\Application\Sales\SalesCreditInvoiceCreator;
use App\Application\Sales\SalesCreditInvoiceDetailReadRepository;
use App\Application\Sales\SalesCreditInvoiceIdentityGenerator;
use App\Application\Sales\SalesCreditInvoiceListReadRepository;
use App\Application\Sales\SalesCreditInvoicePostingClock;
use App\Application\Sales\SalesCreditInvoicePostingIdentityGenerator;
use App\Application\Sales\SalesCreditInvoicePostingRepository;
use App\Application\Sales\SalesCreditInvoicePostingSource;
use App\Application\Sales\SalesCreditInvoiceReadRepository;
use App\Application\Sales\SalesCreditInvoiceUpdater;
use App\Application\Sales\SalesCreditSourceReader;
use App\Application\Sales\SalesCustomerContextReader;
use App\Application\Sales\SalesInvoiceAddressResolver;
use App\Application\Sales\SalesInvoiceCreator;
use App\Application\Sales\SalesInvoiceDetailReadRepository;
use App\Application\Sales\SalesInvoiceListReadRepository;
use App\Application\Sales\SalesInvoicePostingClock;
use App\Application\Sales\SalesInvoicePostingIdentityGenerator;
use App\Application\Sales\SalesInvoicePostingRepository;
use App\Application\Sales\SalesInvoicePostingSource;
use App\Application\Sales\SalesInvoiceReadRepository;
use App\Application\Sales\SalesInvoiceUpdater;
use App\Application\Sales\SalesNumberAllocator;
use App\Application\Sales\SalesNumberSequenceProvisioner;
use App\Application\Sales\SalesPostingConfigurationReader;
use App\Application\Sales\SalesPostingConfigurationStore;
use App\Application\Shared\TransactionManager;
use App\Domain\Accounting\Services\PostingEngine;
use App\Domain\Accounting\Services\PostingValidation;
use App\Domain\Fiscal\Services\TaxCalculation;
use App\Domain\Fiscal\Services\TaxPostingIdentityPolicy;
use App\Domain\Fiscal\Services\TaxPostingReversalPolicy;
use App\Infrastructure\Accounting\LaravelAccountingMasterDataIdentityGenerator;
use App\Infrastructure\Accounting\LaravelOpenItemMatchIdentityGenerator;
use App\Infrastructure\Auth\EloquentAuthAccountStore;
use App\Infrastructure\Auth\LaravelPasswordHasher;
use App\Infrastructure\Development\DemoAccountingProvisioner;
use App\Infrastructure\Fiscal\DutchTaxCodeCatalogueProvisioner;
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
use App\Infrastructure\Persistence\Eloquent\EloquentEligibleSalesCreditSourceReadRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentJournalEntryRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentJournalRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentLedgerAccountRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentMembershipRoleRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentOpenItemRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentOrderInvoicingFacts;
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
use App\Infrastructure\Persistence\Eloquent\EloquentSalesCreditInvoiceReadRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentSalesCreditInvoiceRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentSalesCreditSourceReader;
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
use App\Infrastructure\Sales\EloquentSalesCreditInvoicePostingRepository;
use App\Infrastructure\Sales\EloquentSalesCustomerContextReader;
use App\Infrastructure\Sales\EloquentSalesInvoiceAddressResolver;
use App\Infrastructure\Sales\EloquentSalesInvoicePostingRepository;
use App\Infrastructure\Sales\EloquentSalesPostingConfiguration;
use App\Infrastructure\Sales\LaravelOrderInvoiceDraftIdentityGenerator;
use App\Infrastructure\Sales\LaravelOrderInvoiceLifecycleIdentityGenerator;
use App\Infrastructure\Sales\LaravelQuotationOrderConversionIdentityGenerator;
use App\Infrastructure\Sales\LaravelSalesCreditInvoiceIdentityGenerator;
use App\Infrastructure\Sales\LaravelSalesCreditInvoicePostingIdentityGenerator;
use App\Infrastructure\Sales\LaravelSalesInvoicePostingIdentityGenerator;
use App\Infrastructure\Sales\SystemSalesCreditInvoicePostingClock;
use App\Infrastructure\Sales\SystemSalesInvoicePostingClock;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepository::class, EloquentUserRepository::class);
        $this->app->bind(AccountingMasterDataIdentityGenerator::class, LaravelAccountingMasterDataIdentityGenerator::class);
        $this->app->bind(LedgerAccountReadRepository::class, EloquentLedgerAccountRepository::class);
        $this->app->bind(LedgerAccountStore::class, EloquentLedgerAccountRepository::class);
        $this->app->bind(JournalEntryReadRepository::class, EloquentJournalEntryRepository::class);
        $this->app->bind(JournalEntryStore::class, EloquentJournalEntryRepository::class);
        $this->app->bind(JournalReadRepository::class, EloquentJournalRepository::class);
        $this->app->bind(JournalStore::class, EloquentJournalRepository::class);
        $this->app->bind(OpenItemReadRepository::class, EloquentOpenItemRepository::class);
        $this->app->bind(OpenItemMatchRepository::class, EloquentOpenItemRepository::class);
        $this->app->bind(OpenItemMatchIdentityGenerator::class, LaravelOpenItemMatchIdentityGenerator::class);
        $this->app->bind(OpenItemStore::class, EloquentOpenItemRepository::class);
        $this->app->bind(OpenItemSettlementStore::class, EloquentOpenItemRepository::class);
        $this->app->bind(TaxPostingReadRepository::class, EloquentTaxPostingRepository::class);
        $this->app->bind(TaxPostingStore::class, EloquentTaxPostingRepository::class);
        $this->app->bind(TaxCodeReadRepository::class, EloquentTaxCodeRepository::class);
        $this->app->bind(TaxCodeStore::class, EloquentTaxCodeRepository::class);
        $this->app->bind(TaxCodeCatalogueProvisioner::class, DutchTaxCodeCatalogueProvisioner::class);
        $this->app->bind(RelationReadRepository::class, EloquentRelationRepository::class);
        $this->app->bind(RelationStore::class, EloquentRelationRepository::class);
        $this->app->bind(RelationCreator::class, EloquentRelationRepository::class);
        $this->app->bind(RelationFiscalPartyReader::class, EloquentRelationRepository::class);
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
        $this->app->bind(QuotationOrderConversionSource::class, EloquentQuotationRepository::class);
        $this->app->bind(QuotationCreator::class, EloquentQuotationRepository::class);
        $this->app->bind(QuotationUpdater::class, EloquentQuotationRepository::class);
        $this->app->bind(QuotationListReadRepository::class, EloquentQuotationReadRepository::class);
        $this->app->bind(QuotationDetailReadRepository::class, EloquentQuotationReadRepository::class);
        $this->app->bind(OrderReadRepository::class, EloquentOrderRepository::class);
        $this->app->bind(OrderCreator::class, EloquentOrderRepository::class);
        $this->app->bind(OrderBySourceQuotationRepository::class, EloquentOrderRepository::class);
        $this->app->bind(QuotationOrderConversionIdentityGenerator::class, LaravelQuotationOrderConversionIdentityGenerator::class);
        $this->app->bind(OrderUpdater::class, EloquentOrderRepository::class);
        $this->app->bind(OrderListReadRepository::class, EloquentOrderReadRepository::class);
        $this->app->bind(OrderDetailReadRepository::class, EloquentOrderReadRepository::class);
        $this->app->bind(OrderInvoiceDraftRequestReader::class, EloquentOrderInvoicingFacts::class);
        $this->app->bind(OrderInvoiceDraftIdentityGenerator::class, LaravelOrderInvoiceDraftIdentityGenerator::class);
        $this->app->bind(OrderInvoiceLifecycleIdentityGenerator::class, LaravelOrderInvoiceLifecycleIdentityGenerator::class);
        $this->app->bind(OrderInvoicingFactStore::class, EloquentOrderInvoicingFacts::class);
        $this->app->bind(OrderInvoicingProgressReader::class, EloquentOrderInvoicingFacts::class);
        $this->app->bind(OrderInvoicingOrderLocker::class, EloquentOrderInvoicingFacts::class);
        $this->app->bind(OrderInvoicingSource::class, EloquentOrderRepository::class);
        $this->app->bind(SalesInvoiceReadRepository::class, EloquentSalesInvoiceRepository::class);
        $this->app->bind(SalesInvoiceCreator::class, EloquentSalesInvoiceRepository::class);
        $this->app->bind(SalesInvoiceAddressResolver::class, EloquentSalesInvoiceAddressResolver::class);
        $this->app->bind(SalesInvoiceUpdater::class, EloquentSalesInvoiceRepository::class);
        $this->app->bind(SalesInvoiceListReadRepository::class, EloquentSalesInvoiceReadRepository::class);
        $this->app->bind(SalesInvoiceDetailReadRepository::class, EloquentSalesInvoiceReadRepository::class);
        $this->app->bind(SalesPostingConfigurationReader::class, EloquentSalesPostingConfiguration::class);
        $this->app->bind(SalesPostingConfigurationStore::class, EloquentSalesPostingConfiguration::class);
        $this->app->bind(SalesInvoicePostingRepository::class, EloquentSalesInvoicePostingRepository::class);
        $this->app->bind(SalesInvoicePostingSource::class, EloquentSalesInvoiceRepository::class);
        $this->app->bind(SalesInvoicePostingIdentityGenerator::class, LaravelSalesInvoicePostingIdentityGenerator::class);
        $this->app->bind(SalesInvoicePostingClock::class, SystemSalesInvoicePostingClock::class);
        $this->app->bind(SalesCreditInvoiceReadRepository::class, EloquentSalesCreditInvoiceRepository::class);
        $this->app->bind(SalesCreditInvoiceCreator::class, EloquentSalesCreditInvoiceRepository::class);
        $this->app->bind(SalesCreditInvoiceUpdater::class, EloquentSalesCreditInvoiceRepository::class);
        $this->app->bind(SalesCreditInvoicePostingSource::class, EloquentSalesCreditInvoiceRepository::class);
        $this->app->bind(SalesCreditInvoicePostingRepository::class, EloquentSalesCreditInvoicePostingRepository::class);
        $this->app->bind(SalesCreditInvoicePostingIdentityGenerator::class, LaravelSalesCreditInvoicePostingIdentityGenerator::class);
        $this->app->bind(SalesCreditInvoicePostingClock::class, SystemSalesCreditInvoicePostingClock::class);
        $this->app->bind(SalesCreditInvoiceListReadRepository::class, EloquentSalesCreditInvoiceReadRepository::class);
        $this->app->bind(SalesCreditInvoiceDetailReadRepository::class, EloquentSalesCreditInvoiceReadRepository::class);
        $this->app->bind(SalesCreditSourceReader::class, EloquentSalesCreditSourceReader::class);
        $this->app->bind(SalesCreditInvoiceIdentityGenerator::class, LaravelSalesCreditInvoiceIdentityGenerator::class);
        $this->app->bind(EligibleSalesCreditSourceReadRepository::class, EloquentEligibleSalesCreditSourceReadRepository::class);
        $this->app->bind(PostSalesInvoiceWithTax::class, function ($app): PostSalesInvoiceWithTax {
            $identities = $app->make(SalesInvoicePostingIdentityGenerator::class);

            return new PostSalesInvoiceWithTax(
                new TaxCalculation,
                new TaxPostingIdentityPolicy,
                new PostingEngine(new PostingValidation, fn () => $identities->journalEntryId()),
                new CreateSalesInvoicePostingRequest,
            );
        });
        $this->app->bind(PostSalesCreditInvoiceWithTax::class, function ($app): PostSalesCreditInvoiceWithTax {
            $identities = $app->make(SalesCreditInvoicePostingIdentityGenerator::class);

            return new PostSalesCreditInvoiceWithTax(
                new TaxPostingReversalPolicy,
                new TaxPostingIdentityPolicy,
                new PostingEngine(new PostingValidation, fn () => $identities->journalEntryId()),
            );
        });
        $this->app->bind(RelationClassificationIdentityGenerator::class, LaravelRelationClassificationIdentityGenerator::class);
        $this->app->bind(AdministrationRepository::class, EloquentAdministrationRepository::class);
        $this->app->bind(AdministrationFiscalPartyReader::class, EloquentAdministrationRepository::class);
        $this->app->bind(AdministrationSettingsReader::class, EloquentAdministrationRepository::class);
        $this->app->bind(AdministrationSettingsUpdater::class, EloquentAdministrationRepository::class);
        $this->app->bind(AdministrationMembershipRepository::class, EloquentAdministrationMembershipRepository::class);
        $this->app->bind(RoleRepository::class, EloquentRoleRepository::class);
        $this->app->bind(PermissionRepository::class, EloquentPermissionRepository::class);
        $this->app->bind(RolePermissionRepository::class, EloquentRolePermissionRepository::class);
        $this->app->bind(MembershipRoleRepository::class, EloquentMembershipRoleRepository::class);
        $this->app->bind(AuthorizationReadRepository::class, EloquentAuthorizationReadRepository::class);
        $this->app->bind(AuthAccountStore::class, EloquentAuthAccountStore::class);
        $this->app->bind(PasswordHasher::class, LaravelPasswordHasher::class);
        $this->app->bind(TransactionManager::class, LaravelDatabaseTransactionManager::class);
        $this->app->bind(DevelopmentAccountingMasterDataProvisioner::class, DemoAccountingProvisioner::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
