<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Banking\AdministrationBankAccountWriteResult;
use App\Application\Banking\ManageAdministrationBankAccounts;
use App\Application\Banking\UpdateBankingPostingConfiguration;
use App\Application\Banking\UpdateBankingPostingConfigurationResult;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Banking\ValueObjects\AdministrationBankAccountId;
use App\Domain\Banking\ValueObjects\BankAccountLabel;
use App\Domain\Relations\ValueObjects\AccountName;
use App\Domain\Relations\ValueObjects\Bic;
use App\Domain\Relations\ValueObjects\Iban;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Requests\Administration\StoreAdministrationBankAccountRequest;
use App\Http\Requests\Administration\UpdateAdministrationBankAccountRequest;
use App\Http\Requests\Administration\UpdateBankingPostingConfigurationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

final readonly class BankingSettingsController
{
    public function __construct(private ManageAdministrationBankAccounts $accounts, private UpdateBankingPostingConfiguration $configurations) {}

    public function store(StoreAdministrationBankAccountRequest $request): RedirectResponse
    {
        $data = $request->validated();
        try {
            [$result] = $this->accounts->create($this->context($request)->administration->id(), new Iban($data['iban']), ($data['bic'] ?? null) === null ? null : new Bic($data['bic']), new AccountName($data['account_holder']), new BankAccountLabel($data['label']));
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['bank_account' => 'De operationele bankrekening is ongeldig.']);
        }
        if ($result !== AdministrationBankAccountWriteResult::Success) {
            return back()->withInput()->withErrors(['bank_account' => 'Deze operationele bankrekening bestaat al.']);
        }

        return redirect()->route('settings.administration.edit')->with('status', 'Operationele bankrekening toegevoegd.');
    }

    public function update(UpdateAdministrationBankAccountRequest $request, string $bankAccount): RedirectResponse
    {
        try {
            $id = new AdministrationBankAccountId(new Uuid($bankAccount));
            $data = $request->validated();
            $result = $this->accounts->update($this->context($request)->administration->id(), $id, new AccountName($data['account_holder']), new BankAccountLabel($data['label']));
        } catch (InvalidArgumentException) {
            abort(404);
        }
        abort_if($result === AdministrationBankAccountWriteResult::NotFound, 404);

        return redirect()->route('settings.administration.edit')->with('status', 'Operationele bankrekening bijgewerkt.');
    }

    public function activate(Request $request, string $bankAccount): RedirectResponse
    {
        return $this->status($request, $bankAccount, true);
    }

    public function deactivate(Request $request, string $bankAccount): RedirectResponse
    {
        return $this->status($request, $bankAccount, false);
    }

    public function configure(UpdateBankingPostingConfigurationRequest $request, string $bankAccount): RedirectResponse
    {
        try {
            $data = $request->validated();
            $result = $this->configurations->execute($this->context($request)->administration->id(), new AdministrationBankAccountId(new Uuid($bankAccount)), new JournalId(new Uuid($data['bank_journal_id'])), new LedgerAccountId(new Uuid($data['bank_ledger_account_id'])));
        } catch (InvalidArgumentException) {
            abort(404);
        }
        if ($result === UpdateBankingPostingConfigurationResult::InvalidReference) {
            return back()->withInput()->withErrors(['banking_configuration' => 'Selecteer een actieve bankrekening, Bank-dagboek en Asset-grootboekrekening van deze administratie.']);
        }

        return redirect()->route('settings.administration.edit')->with('status', 'Bankboekingsconfiguratie opgeslagen.');
    }

    private function status(Request $request, string $value, bool $active): RedirectResponse
    {
        try {
            $result = $this->accounts->setActive($this->context($request)->administration->id(), new AdministrationBankAccountId(new Uuid($value)), $active);
        } catch (InvalidArgumentException) {
            abort(404);
        }
        abort_if($result === AdministrationBankAccountWriteResult::NotFound, 404);

        return redirect()->route('settings.administration.edit')->with('status', $active ? 'Operationele bankrekening geactiveerd.' : 'Operationele bankrekening gedeactiveerd.');
    }

    private function context(Request $request): ActiveAdministrationContext
    {
        return $request->attributes->get('administration_context');
    }
}
