<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Administration\AdministrationSettingsWriteResult;
use App\Application\Administration\GetAdministrationSettings;
use App\Application\Administration\UpdateAdministrationSettings;
use App\Domain\Administration\ValueObjects\AdministrationName;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Shared\Fiscal\VatIdentificationNumber;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Requests\Administration\UpdateAdministrationSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

final readonly class AdministrationSettingsController
{
    public function __construct(
        private GetAdministrationSettings $getSettings,
        private UpdateAdministrationSettings $updateSettings,
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
        ]);
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

    private function context(Request $request): ActiveAdministrationContext
    {
        /** @var ActiveAdministrationContext */
        return $request->attributes->get('administration_context');
    }
}
