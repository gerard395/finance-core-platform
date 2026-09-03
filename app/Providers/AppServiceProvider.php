<?php

namespace App\Providers;

use App\Application\Accounting\AccountingMasterDataIdentityGenerator;
use App\Application\Accounting\AccountingPeriodHistoryReadRepository;
use App\Application\Accounting\AccountingPeriodLookupRepository;
use App\Application\Accounting\AccountingPeriodPlanIdentityGenerator;
use App\Application\Accounting\AccountingPeriodPostingGuard;
use App\Application\Accounting\BookYearRepository;
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
use App\Application\Banking\AdministrationBankAccountIdentityGenerator;
use App\Application\Banking\AdministrationBankAccountRepository;
use App\Application\Banking\BankEntryManualHistoryRepository;
use App\Application\Banking\BankEntryReconciliationClock;
use App\Application\Banking\BankEntryReconciliationIdentityGenerator;
use App\Application\Banking\BankImportArtifactKeyGenerator;
use App\Application\Banking\BankImportArtifactStorage;
use App\Application\Banking\BankImportClock;
use App\Application\Banking\BankImportSourceIdentityGenerator;
use App\Application\Banking\BankImportSourceRepository;
use App\Application\Banking\BankingOpenItemLocker;
use App\Application\Banking\BankingPostingConfigurationReader;
use App\Application\Banking\BankingPostingConfigurationStore;
use App\Application\Banking\BankPostingIdentityGenerator;
use App\Application\Banking\BankReconciliationCandidateReader;
use App\Application\Banking\BankReconciliationSourceReader;
use App\Application\Banking\BankStatementParser;
use App\Application\Banking\BankTransactionClock;
use App\Application\Banking\BankTransactionIdentityGenerator;
use App\Application\Banking\BankTransactionPostingRepository;
use App\Application\Banking\BankTransactionRepository;
use App\Application\Banking\BankTransactionReversalIdentityGenerator;
use App\Application\Banking\BankTransactionReversalRepository;
use App\Application\Banking\BankTransactionReversalSourceReader;
use App\Application\Banking\BankTransactionSettlementReversalLinkRepository;
use App\Application\Development\DevelopmentAccountingMasterDataProvisioner;
use App\Application\Fiscal\TaxCodeCatalogueProvisioner;
use App\Application\Fiscal\TaxCodeReadRepository;
use App\Application\Fiscal\TaxCodeStore;
use App\Application\Fiscal\TaxPostingReadRepository;
use App\Application\Fiscal\TaxPostingStore;
use App\Application\Fiscal\TaxTreatmentDefinitionRepository;
use App\Application\Identity\AdministrationMembershipRepository;
use App\Application\Identity\AuthAccountStore;
use App\Application\Identity\AuthorizationReadRepository;
use App\Application\Identity\MembershipRoleRepository;
use App\Application\Identity\PasswordHasher;
use App\Application\Identity\PermissionRepository;
use App\Application\Identity\RolePermissionRepository;
use App\Application\Identity\RoleRepository;
use App\Application\Identity\UserRepository;
use App\Application\Purchasing\PostPurchaseCreditInvoiceWithTax;
use App\Application\Purchasing\PostPurchaseInvoice;
use App\Application\Purchasing\PurchaseCreditClaimReader;
use App\Application\Purchasing\PurchaseCreditClock;
use App\Application\Purchasing\PurchaseCreditHistoricalPostingReader;
use App\Application\Purchasing\PurchaseCreditIdentityGenerator;
use App\Application\Purchasing\PurchaseCreditInvoiceRepository;
use App\Application\Purchasing\PurchaseCreditPostingRepository;
use App\Application\Purchasing\PurchaseCreditSourceReader;
use App\Application\Purchasing\PurchaseInvoiceClock;
use App\Application\Purchasing\PurchaseInvoiceIdentityGenerator;
use App\Application\Purchasing\PurchaseInvoiceMasterDataReader;
use App\Application\Purchasing\PurchaseInvoicePostingClock;
use App\Application\Purchasing\PurchaseInvoicePostingIdentityGenerator;
use App\Application\Purchasing\PurchaseInvoicePostingRepository;
use App\Application\Purchasing\PurchaseInvoiceRepository;
use App\Application\Purchasing\PurchasePostingConfigurationReader;
use App\Application\Purchasing\PurchasePostingConfigurationStore;
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
use App\Application\Sales\DeliveryIdentityGenerator;
use App\Application\Sales\DeliveryOutboxStore;
use App\Application\Sales\DeliveryOutcomeResolutionStore;
use App\Application\Sales\DeliveryRequestStore;
use App\Application\Sales\DeliveryWorkerHeartbeatStore;
use App\Application\Sales\DocumentArtifactFailureReporter;
use App\Application\Sales\DocumentArtifactRepository;
use App\Application\Sales\DocumentArtifactStorage;
use App\Application\Sales\DocumentMailTransport;
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
use App\Application\Sales\QuotationAddressResolver;
use App\Application\Sales\QuotationCreator;
use App\Application\Sales\QuotationDeliveryLifecycleCandidates;
use App\Application\Sales\QuotationDetailReadRepository;
use App\Application\Sales\QuotationListReadRepository;
use App\Application\Sales\QuotationOrderConversionIdentityGenerator;
use App\Application\Sales\QuotationOrderConversionSource;
use App\Application\Sales\QuotationReadRepository;
use App\Application\Sales\QuotationUpdater;
use App\Application\Sales\RenderModelSalesDocumentReadinessChecker;
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
use App\Application\Sales\SalesDocumentArtifactIdentityGenerator;
use App\Application\Sales\SalesDocumentDeliveryHistoryReader;
use App\Application\Sales\SalesDocumentDeliveryInfrastructureReadiness;
use App\Application\Sales\SalesDocumentDeliverySourceReader;
use App\Application\Sales\SalesDocumentIssuerReader;
use App\Application\Sales\SalesDocumentMasterDataStore;
use App\Application\Sales\SalesDocumentPdfRenderer;
use App\Application\Sales\SalesDocumentReadinessChecker;
use App\Application\Sales\SalesDocumentRecipientPreferenceStore;
use App\Application\Sales\SalesDocumentRecipientReader;
use App\Application\Sales\SalesDocumentSenderReader;
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
use App\Domain\Fiscal\Services\PurchaseTaxTreatmentCalculation;
use App\Domain\Fiscal\Services\TaxCalculation;
use App\Domain\Fiscal\Services\TaxPostingIdentityPolicy;
use App\Domain\Fiscal\Services\TaxPostingReversalPolicy;
use App\Infrastructure\Accounting\EloquentBookYearRepository;
use App\Infrastructure\Accounting\LaravelAccountingMasterDataIdentityGenerator;
use App\Infrastructure\Accounting\LaravelAccountingPeriodPlanIdentityGenerator;
use App\Infrastructure\Accounting\LaravelOpenItemMatchIdentityGenerator;
use App\Infrastructure\Auth\EloquentAuthAccountStore;
use App\Infrastructure\Auth\LaravelPasswordHasher;
use App\Infrastructure\Banking\Camt053Parser;
use App\Infrastructure\Banking\EloquentAdministrationBankAccountRepository;
use App\Infrastructure\Banking\EloquentBankEntryManualHistoryRepository;
use App\Infrastructure\Banking\EloquentBankImportSourceRepository;
use App\Infrastructure\Banking\EloquentBankingPostingConfiguration;
use App\Infrastructure\Banking\EloquentBankReconciliationCandidateReader;
use App\Infrastructure\Banking\EloquentBankReconciliationSourceReader;
use App\Infrastructure\Banking\EloquentBankTransactionPostingRepository;
use App\Infrastructure\Banking\EloquentBankTransactionRepository;
use App\Infrastructure\Banking\EloquentBankTransactionReversalRepository;
use App\Infrastructure\Banking\LaravelAdministrationBankAccountIdentityGenerator;
use App\Infrastructure\Banking\LaravelBankEntryReconciliationIdentityGenerator;
use App\Infrastructure\Banking\LaravelBankImportArtifactKeyGenerator;
use App\Infrastructure\Banking\LaravelBankImportArtifactStorage;
use App\Infrastructure\Banking\LaravelBankImportSourceIdentityGenerator;
use App\Infrastructure\Banking\LaravelBankPostingIdentityGenerator;
use App\Infrastructure\Banking\LaravelBankTransactionIdentityGenerator;
use App\Infrastructure\Banking\LaravelBankTransactionReversalIdentityGenerator;
use App\Infrastructure\Banking\SystemBankEntryReconciliationClock;
use App\Infrastructure\Banking\SystemBankImportClock;
use App\Infrastructure\Banking\SystemBankTransactionClock;
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
use App\Infrastructure\Persistence\Eloquent\EloquentDocumentArtifactRepository;
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
use App\Infrastructure\Persistence\Eloquent\EloquentSalesDocumentDelivery;
use App\Infrastructure\Persistence\Eloquent\EloquentSalesInvoiceReadRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentSalesInvoiceRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentSupplierRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentTaxCodeRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentTaxPostingRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentTaxTreatmentDefinitionRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentUserRepository;
use App\Infrastructure\Persistence\LaravelDatabaseTransactionManager;
use App\Infrastructure\Purchasing\EloquentPurchaseCreditClaimReader;
use App\Infrastructure\Purchasing\EloquentPurchaseCreditHistoricalPostingReader;
use App\Infrastructure\Purchasing\EloquentPurchaseCreditInvoiceRepository;
use App\Infrastructure\Purchasing\EloquentPurchaseCreditPostingRepository;
use App\Infrastructure\Purchasing\EloquentPurchaseCreditSourceReader;
use App\Infrastructure\Purchasing\EloquentPurchaseInvoiceMasterDataReader;
use App\Infrastructure\Purchasing\EloquentPurchaseInvoicePostingRepository;
use App\Infrastructure\Purchasing\EloquentPurchaseInvoiceRepository;
use App\Infrastructure\Purchasing\EloquentPurchasePostingConfiguration;
use App\Infrastructure\Purchasing\LaravelPurchaseCreditIdentityGenerator;
use App\Infrastructure\Purchasing\LaravelPurchaseInvoiceIdentityGenerator;
use App\Infrastructure\Purchasing\LaravelPurchaseInvoicePostingIdentityGenerator;
use App\Infrastructure\Purchasing\SystemPurchaseCreditClock;
use App\Infrastructure\Purchasing\SystemPurchaseInvoiceClock;
use App\Infrastructure\Purchasing\SystemPurchaseInvoicePostingClock;
use App\Infrastructure\Relations\DatabaseRelationNumberSequence;
use App\Infrastructure\Relations\LaravelRelationClassificationIdentityGenerator;
use App\Infrastructure\Sales\BrowsershotSalesDocumentPdfRenderer;
use App\Infrastructure\Sales\DatabaseDeliveryOperations;
use App\Infrastructure\Sales\DatabaseSalesNumberSequence;
use App\Infrastructure\Sales\EloquentQuotationAddressResolver;
use App\Infrastructure\Sales\EloquentSalesCreditInvoicePostingRepository;
use App\Infrastructure\Sales\EloquentSalesCustomerContextReader;
use App\Infrastructure\Sales\EloquentSalesDocumentMasterData;
use App\Infrastructure\Sales\EloquentSalesDocumentRecipientPreferences;
use App\Infrastructure\Sales\EloquentSalesInvoiceAddressResolver;
use App\Infrastructure\Sales\EloquentSalesInvoicePostingRepository;
use App\Infrastructure\Sales\EloquentSalesPostingConfiguration;
use App\Infrastructure\Sales\LaravelDeliveryIdentityGenerator;
use App\Infrastructure\Sales\LaravelDocumentArtifactFailureReporter;
use App\Infrastructure\Sales\LaravelDocumentArtifactStorage;
use App\Infrastructure\Sales\LaravelDocumentMailTransport;
use App\Infrastructure\Sales\LaravelOrderInvoiceDraftIdentityGenerator;
use App\Infrastructure\Sales\LaravelOrderInvoiceLifecycleIdentityGenerator;
use App\Infrastructure\Sales\LaravelQuotationOrderConversionIdentityGenerator;
use App\Infrastructure\Sales\LaravelSalesCreditInvoiceIdentityGenerator;
use App\Infrastructure\Sales\LaravelSalesCreditInvoicePostingIdentityGenerator;
use App\Infrastructure\Sales\LaravelSalesDocumentArtifactIdentityGenerator;
use App\Infrastructure\Sales\LaravelSalesInvoicePostingIdentityGenerator;
use App\Infrastructure\Sales\SystemSalesCreditInvoicePostingClock;
use App\Infrastructure\Sales\SystemSalesInvoicePostingClock;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BankStatementParser::class, Camt053Parser::class);
        $this->app->bind(BankImportSourceIdentityGenerator::class, LaravelBankImportSourceIdentityGenerator::class);
        $this->app->bind(BankImportArtifactStorage::class, LaravelBankImportArtifactStorage::class);
        $this->app->bind(BankImportArtifactKeyGenerator::class, LaravelBankImportArtifactKeyGenerator::class);
        $this->app->bind(BankImportSourceRepository::class, EloquentBankImportSourceRepository::class);
        $this->app->bind(BankEntryManualHistoryRepository::class, EloquentBankEntryManualHistoryRepository::class);
        $this->app->bind(BankEntryReconciliationIdentityGenerator::class, LaravelBankEntryReconciliationIdentityGenerator::class);
        $this->app->bind(BankEntryReconciliationClock::class, SystemBankEntryReconciliationClock::class);
        $this->app->bind(BankReconciliationSourceReader::class, EloquentBankReconciliationSourceReader::class);
        $this->app->bind(BankReconciliationCandidateReader::class, EloquentBankReconciliationCandidateReader::class);
        $this->app->bind(BankImportClock::class, SystemBankImportClock::class);
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
        $this->app->bind(TaxTreatmentDefinitionRepository::class, EloquentTaxTreatmentDefinitionRepository::class);
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
        $this->app->bind(QuotationAddressResolver::class, EloquentQuotationAddressResolver::class);
        $this->app->bind(SalesDocumentRecipientReader::class, EloquentSalesDocumentRecipientPreferences::class);
        $this->app->bind(SalesDocumentRecipientPreferenceStore::class, EloquentSalesDocumentRecipientPreferences::class);
        $this->app->bind(SalesDocumentIssuerReader::class, EloquentSalesDocumentMasterData::class);
        $this->app->bind(SalesDocumentSenderReader::class, EloquentSalesDocumentMasterData::class);
        $this->app->bind(SalesDocumentMasterDataStore::class, EloquentSalesDocumentMasterData::class);
        $this->app->bind(SalesDocumentPdfRenderer::class, BrowsershotSalesDocumentPdfRenderer::class);
        $this->app->bind(SalesDocumentReadinessChecker::class, RenderModelSalesDocumentReadinessChecker::class);
        $this->app->bind(DocumentArtifactStorage::class, LaravelDocumentArtifactStorage::class);
        $this->app->bind(DocumentArtifactRepository::class, EloquentDocumentArtifactRepository::class);
        $this->app->bind(DocumentArtifactFailureReporter::class, LaravelDocumentArtifactFailureReporter::class);
        $this->app->bind(SalesDocumentArtifactIdentityGenerator::class, LaravelSalesDocumentArtifactIdentityGenerator::class);
        $this->app->bind(DeliveryIdentityGenerator::class, LaravelDeliveryIdentityGenerator::class);
        $this->app->bind(DocumentMailTransport::class, LaravelDocumentMailTransport::class);
        $this->app->bind(DeliveryRequestStore::class, EloquentSalesDocumentDelivery::class);
        $this->app->bind(DeliveryOutboxStore::class, EloquentSalesDocumentDelivery::class);
        $this->app->bind(SalesDocumentDeliverySourceReader::class, EloquentSalesDocumentDelivery::class);
        $this->app->bind(SalesDocumentDeliveryHistoryReader::class, EloquentSalesDocumentDelivery::class);
        $this->app->bind(QuotationDeliveryLifecycleCandidates::class, EloquentSalesDocumentDelivery::class);
        $this->app->bind(DeliveryWorkerHeartbeatStore::class, DatabaseDeliveryOperations::class);
        $this->app->bind(SalesDocumentDeliveryInfrastructureReadiness::class, DatabaseDeliveryOperations::class);
        $this->app->bind(DeliveryOutcomeResolutionStore::class, DatabaseDeliveryOperations::class);
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
        $this->app->bind(PurchasePostingConfigurationReader::class, EloquentPurchasePostingConfiguration::class);
        $this->app->bind(PurchasePostingConfigurationStore::class, EloquentPurchasePostingConfiguration::class);
        $this->app->bind(AdministrationBankAccountRepository::class, EloquentAdministrationBankAccountRepository::class);
        $this->app->bind(BankTransactionRepository::class, EloquentBankTransactionRepository::class);
        $this->app->bind(BankTransactionReversalRepository::class, EloquentBankTransactionReversalRepository::class);
        $this->app->bind(BankTransactionReversalIdentityGenerator::class, LaravelBankTransactionReversalIdentityGenerator::class);
        $this->app->bind(BankTransactionSettlementReversalLinkRepository::class, EloquentBankTransactionReversalRepository::class);
        $this->app->bind(BankTransactionReversalSourceReader::class, EloquentBankTransactionReversalRepository::class);
        $this->app->bind(BankTransactionIdentityGenerator::class, LaravelBankTransactionIdentityGenerator::class);
        $this->app->bind(BankTransactionClock::class, SystemBankTransactionClock::class);
        $this->app->bind(BankTransactionPostingRepository::class, EloquentBankTransactionPostingRepository::class);
        $this->app->bind(BankPostingIdentityGenerator::class, LaravelBankPostingIdentityGenerator::class);
        $this->app->bind(BankingOpenItemLocker::class, EloquentOpenItemRepository::class);
        $this->app->bind(AdministrationBankAccountIdentityGenerator::class, LaravelAdministrationBankAccountIdentityGenerator::class);
        $this->app->bind(BankingPostingConfigurationReader::class, EloquentBankingPostingConfiguration::class);
        $this->app->bind(BankingPostingConfigurationStore::class, EloquentBankingPostingConfiguration::class);
        $this->app->bind(PurchaseInvoiceRepository::class, EloquentPurchaseInvoiceRepository::class);
        $this->app->bind(PurchaseCreditInvoiceRepository::class, EloquentPurchaseCreditInvoiceRepository::class);
        $this->app->bind(PurchaseCreditClaimReader::class, EloquentPurchaseCreditClaimReader::class);
        $this->app->bind(PurchaseCreditSourceReader::class, EloquentPurchaseCreditSourceReader::class);
        $this->app->bind(PurchaseCreditHistoricalPostingReader::class, EloquentPurchaseCreditHistoricalPostingReader::class);
        $this->app->bind(PurchaseCreditPostingRepository::class, EloquentPurchaseCreditPostingRepository::class);
        $this->app->bind(PurchaseCreditIdentityGenerator::class, LaravelPurchaseCreditIdentityGenerator::class);
        $this->app->bind(PurchaseCreditClock::class, SystemPurchaseCreditClock::class);
        $this->app->bind(PostPurchaseCreditInvoiceWithTax::class, function ($app): PostPurchaseCreditInvoiceWithTax {
            $identities = $app->make(PurchaseCreditIdentityGenerator::class);

            return new PostPurchaseCreditInvoiceWithTax(
                new TaxPostingReversalPolicy,
                new TaxPostingIdentityPolicy,
                new PostingEngine(new PostingValidation, fn () => $identities->journalEntryId()),
            );
        });
        $this->app->bind(PurchaseInvoiceMasterDataReader::class, EloquentPurchaseInvoiceMasterDataReader::class);
        $this->app->bind(PurchaseInvoiceIdentityGenerator::class, LaravelPurchaseInvoiceIdentityGenerator::class);
        $this->app->bind(PurchaseInvoiceClock::class, SystemPurchaseInvoiceClock::class);
        $this->app->bind(PurchaseInvoicePostingRepository::class, EloquentPurchaseInvoicePostingRepository::class);
        $this->app->bind(PurchaseInvoicePostingIdentityGenerator::class, LaravelPurchaseInvoicePostingIdentityGenerator::class);
        $this->app->bind(PurchaseInvoicePostingClock::class, SystemPurchaseInvoicePostingClock::class);
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
        $this->app->bind(PostPurchaseInvoice::class, function ($app): PostPurchaseInvoice {
            $identities = $app->make(PurchaseInvoicePostingIdentityGenerator::class);

            return new PostPurchaseInvoice(
                $app->make(TransactionManager::class),
                $app->make(PurchaseInvoiceRepository::class),
                $app->make(PurchaseInvoicePostingRepository::class),
                $app->make(PurchasePostingConfigurationReader::class),
                $app->make(PurchaseInvoiceMasterDataReader::class),
                new PostingEngine(new PostingValidation, fn () => $identities->journalEntryId()),
                $app->make(JournalEntryStore::class),
                $app->make(TaxPostingStore::class),
                $app->make(OpenItemStore::class),
                $identities,
                $app->make(PurchaseInvoicePostingClock::class),
                $app->make(AccountingPeriodPostingGuard::class),
                new PurchaseTaxTreatmentCalculation,
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
        $this->app->bind(BookYearRepository::class, EloquentBookYearRepository::class);
        $this->app->bind(AccountingPeriodHistoryReadRepository::class, EloquentBookYearRepository::class);
        $this->app->bind(AccountingPeriodLookupRepository::class, EloquentBookYearRepository::class);
        $this->app->bind(AccountingPeriodPlanIdentityGenerator::class, LaravelAccountingPeriodPlanIdentityGenerator::class);
        $this->app->bind(DevelopmentAccountingMasterDataProvisioner::class, DemoAccountingProvisioner::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(Looping::class, function (): void {
            app(DeliveryWorkerHeartbeatStore::class)->beat('sales-document-delivery:'.getmypid(), env('APP_RELEASE'));
        });
    }
}
