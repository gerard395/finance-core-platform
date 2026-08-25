<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Administration\AdministrationSettingsWriteResult;
use App\Application\Administration\GetAdministrationSettings;
use App\Application\Administration\UpdateAdministrationSettings;
use App\Application\Fiscal\TaxCodeCatalogueProvisioner;
use App\Application\Fiscal\TaxCodeReadRepository;
use App\Application\Purchasing\GetPurchasePostingConfigurationSettings;
use App\Application\Purchasing\UpdatePurchasePostingConfiguration;
use App\Application\Purchasing\UpdatePurchasePostingConfigurationResult;
use App\Application\Sales\GetSalesPostingConfigurationSettings;
use App\Application\Sales\SalesDocumentMasterData;
use App\Application\Sales\SalesDocumentMasterDataStore;
use App\Application\Sales\UpdateSalesDocumentMasterData;
use App\Application\Sales\UpdateSalesPostingConfiguration;
use App\Application\Sales\UpdateSalesPostingConfigurationResult;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Administration\ValueObjects\AdministrationName;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Relations\ValueObjects\AddressLine;
use App\Domain\Relations\ValueObjects\Bic;
use App\Domain\Relations\ValueObjects\City;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\EmailAddress;
use App\Domain\Relations\ValueObjects\Iban;
use App\Domain\Relations\ValueObjects\PostalCode;
use App\Domain\Shared\Fiscal\VatIdentificationNumber;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Requests\Administration\UpdateAdministrationSettingsRequest;
use App\Http\Requests\Administration\UpdatePurchasePostingConfigurationRequest;
use App\Http\Requests\Administration\UpdateSalesDocumentMasterDataRequest;
use App\Http\Requests\Administration\UpdateSalesPostingConfigurationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

final readonly class AdministrationSettingsController
{
    public function __construct(
        private GetAdministrationSettings $getSettings,
        private UpdateAdministrationSettings $updateSettings,
        private GetSalesPostingConfigurationSettings $getSalesPostingSettings,
        private UpdateSalesPostingConfiguration $updateSalesPostingSettings,
        private GetPurchasePostingConfigurationSettings $getPurchasePostingSettings,
        private UpdatePurchasePostingConfiguration $updatePurchasePostingSettings,
        private TaxCodeCatalogueProvisioner $taxCodeCatalogue,
        private TaxCodeReadRepository $taxCodes,
        private SalesDocumentMasterDataStore $documentSettings,
        private UpdateSalesDocumentMasterData $updateDocumentSettings,
    ) {}

    public function edit(Request $request): View
    {
        $context = $this->context($request);
        $settings = $this->getSettings->execute($context->administration->id());
        abort_if($settings === null, 404);

        return view('settings.administration', [
            'domainUser' => $context->user,
            'administrationContext' => $context,
            'settings' => $settings,
            'salesPostingSettings' => $this->getSalesPostingSettings->execute($context->administration->id()),
            'purchasePostingSettings' => $this->getPurchasePostingSettings->execute($context->administration->id()),
            'inputTaxCodes' => $this->taxCodes->findActiveForAdministrationAndDirection($context->administration->id(), TaxPostingDirection::Input),
            'documentSettings' => $this->documentSettings->readMasterData($context->administration->id()),
        ]);
    }

    public function updatePurchasePosting(UpdatePurchasePostingConfigurationRequest $request): RedirectResponse
    {
        $context = $this->context($request);
        $data = $request->validated();
        try {
            $result = $this->updatePurchasePostingSettings->execute(
                $context->administration->id(),
                new JournalId(new Uuid($data['purchase_journal_id'])),
                new LedgerAccountId(new Uuid($data['accounts_payable_ledger_account_id'])),
                new LedgerAccountId(new Uuid($data['input_vat_ledger_account_id'])),
            );
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['purchase_posting' => 'De inkoopboekingsinstellingen zijn ongeldig.']);
        }
        if ($result === UpdatePurchasePostingConfigurationResult::InvalidReference) {
            return back()->withInput()->withErrors(['purchase_posting' => 'Selecteer uitsluitend geldige, actieve dagboeken en grootboekrekeningen van deze administratie.']);
        }

        return redirect()->route('settings.administration.edit')->with('status', 'Inkoopboekingsinstellingen opgeslagen.');
    }

    public function provisionPurchaseTaxCodes(Request $request): RedirectResponse
    {
        $context = $this->context($request);
        $this->taxCodeCatalogue->ensureDutchBasicInputForAdministration($context->administration->id());

        return redirect()->route('settings.administration.edit')->with('status', 'Binnenlandse voorbelastingcodes beschikbaar gemaakt.');
    }

    public function updateSalesPosting(UpdateSalesPostingConfigurationRequest $request): RedirectResponse
    {
        $context = $this->context($request);
        $validated = $request->validated();

        try {
            $result = $this->updateSalesPostingSettings->execute(
                $context->administration->id(),
                new JournalId(new Uuid($validated['sales_journal_id'])),
                new LedgerAccountId(new Uuid($validated['accounts_receivable_ledger_account_id'])),
                new LedgerAccountId(new Uuid($validated['revenue_ledger_account_id'])),
                new LedgerAccountId(new Uuid($validated['output_vat_ledger_account_id'])),
            );
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['sales_posting' => 'De verkoopboekingsinstellingen zijn ongeldig.']);
        }

        if ($result === UpdateSalesPostingConfigurationResult::InvalidReference) {
            return back()->withInput()->withErrors(['sales_posting' => 'Selecteer uitsluitend geldige, actieve dagboeken en grootboekrekeningen van deze administratie.']);
        }

        return redirect()->route('settings.administration.edit')->with('status', 'Verkoopboekingsinstellingen opgeslagen.');
    }

    public function update(UpdateAdministrationSettingsRequest $request): RedirectResponse
    {
        $context = $this->context($request);
        $validated = $request->validated();

        try {
            $result = $this->updateSettings->execute(
                $context->administration->id(),
                new AdministrationName($validated['name']),
                $validated['description'],
                ($validated['vat_identification_number'] ?? null) === null ? null : new VatIdentificationNumber($validated['vat_identification_number']),
                ($validated['fiscal_jurisdiction'] ?? null) === null ? null : new CountryCode($validated['fiscal_jurisdiction']),
                true,
            );
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['name' => 'De administratiegegevens zijn ongeldig.']);
        }

        abort_if($result === AdministrationSettingsWriteResult::NotFound, 404);

        return redirect()->route('settings.administration.edit')->with('status', 'Instellingen opgeslagen.');
    }

    public function updateDocumentSettings(UpdateSalesDocumentMasterDataRequest $request): RedirectResponse
    {
        $context = $this->context($request);
        $data = $request->validated();
        try {
            $settings = new SalesDocumentMasterData(
                $data['display_name'], $data['legal_name'], $data['registration_number'],
                $this->optional($data['address_line_1'], AddressLine::class), $this->optional($data['address_line_2'], AddressLine::class),
                $this->optional($data['postal_code'], PostalCode::class), $this->optional($data['city'], City::class),
                $this->optional($data['country_code'], CountryCode::class), $this->optional($data['business_email'], EmailAddress::class),
                $data['business_phone'], $data['website'], $this->optional($data['iban'], Iban::class),
                $this->optional($data['bic'], Bic::class), $data['account_holder'], $data['sender_name'],
                $this->optional($data['sender_email'], EmailAddress::class), $this->optional($data['reply_to_email'], EmailAddress::class),
            );
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['document_settings' => 'De documentinstellingen zijn ongeldig.']);
        }
        abort_unless($this->updateDocumentSettings->execute($context->administration->id(), $settings), 404);

        return redirect()->route('settings.administration.edit')->with('status', 'Documentinstellingen opgeslagen.');
    }

    /** @template T of object
     * @param  class-string<T>  $class
     * @return T|null
     */
    private function optional(?string $value, string $class): ?object
    {
        return $value === null ? null : new $class($value);
    }

    private function context(Request $request): ActiveAdministrationContext
    {
        /** @var ActiveAdministrationContext */
        return $request->attributes->get('administration_context');
    }
}
