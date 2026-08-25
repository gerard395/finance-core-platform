<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Accounting\AccountingMasterDataWriteResult;
use App\Application\Accounting\CreateJournal;
use App\Application\Accounting\CreateLedgerAccount;
use App\Application\Accounting\GetJournalMasterData;
use App\Application\Accounting\GetLedgerAccountMasterData;
use App\Application\Accounting\SetJournalStatus;
use App\Application\Accounting\SetLedgerAccountStatus;
use App\Application\Accounting\UpdateJournal;
use App\Application\Accounting\UpdateLedgerAccount;
use App\Domain\Accounting\Entities\Journal;
use App\Domain\Accounting\Entities\LedgerAccount;
use App\Domain\Accounting\Enums\JournalStatus;
use App\Domain\Accounting\Enums\JournalType;
use App\Domain\Accounting\Enums\LedgerAccountStatus;
use App\Domain\Accounting\Enums\LedgerAccountType;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Requests\Accounting\CreateJournalRequest;
use App\Http\Requests\Accounting\CreateLedgerAccountRequest;
use App\Http\Requests\Accounting\UpdateMasterDataNameRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

final readonly class AccountingMasterDataController
{
    public function __construct(
        private GetJournalMasterData $journals,
        private GetLedgerAccountMasterData $accounts,
        private CreateJournal $createJournal,
        private UpdateJournal $updateJournal,
        private SetJournalStatus $setJournalStatus,
        private CreateLedgerAccount $createAccount,
        private UpdateLedgerAccount $updateAccount,
        private SetLedgerAccountStatus $setAccountStatus,
    ) {}

    public function journals(Request $request): View
    {
        $context = $this->context($request);

        return view('settings.journals.index', $this->base($context) + ['journals' => $this->journals->list($context->administration->id())]);
    }

    public function createJournal(Request $request): View
    {
        return view('settings.journals.create', $this->base($this->context($request)) + ['types' => JournalType::cases()]);
    }

    public function storeJournal(CreateJournalRequest $request): RedirectResponse
    {
        $data = $request->validated();

        return $this->mutation($this->createJournal->execute($this->context($request)->administration->id(), $data['code'], $data['name'], JournalType::from($data['type'])), 'settings.journals.index', 'Dagboek aangemaakt.');
    }

    public function editJournal(Request $request, string $journal): View
    {
        $context = $this->context($request);
        $record = $this->journal($context, $journal);

        return view('settings.journals.edit', $this->base($context) + ['journal' => $record]);
    }

    public function updateJournal(UpdateMasterDataNameRequest $request, string $journal): RedirectResponse
    {
        $context = $this->context($request);

        return $this->mutation($this->updateJournal->execute($context->administration->id(), $this->journalId($journal), $request->validated()['name']), 'settings.journals.index', 'Dagboek bijgewerkt.');
    }

    public function activateJournal(Request $request, string $journal): RedirectResponse
    {
        return $this->journalStatus($request, $journal, JournalStatus::Active);
    }

    public function deactivateJournal(Request $request, string $journal): RedirectResponse
    {
        return $this->journalStatus($request, $journal, JournalStatus::Inactive);
    }

    public function accounts(Request $request): View
    {
        $context = $this->context($request);

        return view('settings.ledger-accounts.index', $this->base($context) + ['accounts' => $this->accounts->list($context->administration->id())]);
    }

    public function createAccount(Request $request): View
    {
        return view('settings.ledger-accounts.create', $this->base($this->context($request)) + ['types' => LedgerAccountType::cases()]);
    }

    public function storeAccount(CreateLedgerAccountRequest $request): RedirectResponse
    {
        $data = $request->validated();

        return $this->mutation($this->createAccount->execute($this->context($request)->administration->id(), $data['code'], $data['name'], LedgerAccountType::from($data['type'])), 'settings.ledger-accounts.index', 'Grootboekrekening aangemaakt.');
    }

    public function editAccount(Request $request, string $account): View
    {
        $context = $this->context($request);
        $record = $this->account($context, $account);

        return view('settings.ledger-accounts.edit', $this->base($context) + ['account' => $record]);
    }

    public function updateAccount(UpdateMasterDataNameRequest $request, string $account): RedirectResponse
    {
        $context = $this->context($request);

        return $this->mutation($this->updateAccount->execute($context->administration->id(), $this->accountId($account), $request->validated()['name']), 'settings.ledger-accounts.index', 'Grootboekrekening bijgewerkt.');
    }

    public function activateAccount(Request $request, string $account): RedirectResponse
    {
        return $this->accountStatus($request, $account, LedgerAccountStatus::Active);
    }

    public function deactivateAccount(Request $request, string $account): RedirectResponse
    {
        return $this->accountStatus($request, $account, LedgerAccountStatus::Inactive);
    }

    private function journalStatus(Request $request, string $id, JournalStatus $status): RedirectResponse
    {
        return $this->mutation($this->setJournalStatus->execute($this->context($request)->administration->id(), $this->journalId($id), $status), 'settings.journals.index', $status === JournalStatus::Active ? 'Dagboek geactiveerd.' : 'Dagboek gedeactiveerd.');
    }

    private function accountStatus(Request $request, string $id, LedgerAccountStatus $status): RedirectResponse
    {
        return $this->mutation($this->setAccountStatus->execute($this->context($request)->administration->id(), $this->accountId($id), $status), 'settings.ledger-accounts.index', $status === LedgerAccountStatus::Active ? 'Grootboekrekening geactiveerd.' : 'Grootboekrekening gedeactiveerd.');
    }

    private function mutation(AccountingMasterDataWriteResult $result, string $route, string $message): RedirectResponse
    {
        if ($result === AccountingMasterDataWriteResult::NotFound) {
            abort(404);
        }
        if ($result === AccountingMasterDataWriteResult::Success) {
            return redirect()->route($route)->with('status', $message);
        }
        $error = $result === AccountingMasterDataWriteResult::DuplicateCode ? 'Deze code bestaat al binnen de administratie.' : 'De masterdata kon niet veilig worden opgeslagen.';

        return back()->withInput()->withErrors(['master_data' => $error]);
    }

    private function journal(ActiveAdministrationContext $context, string $id): Journal
    {
        $journal = $this->journals->find($context->administration->id(), $this->journalId($id));
        abort_if($journal === null, 404);

        return $journal;
    }

    private function account(ActiveAdministrationContext $context, string $id): LedgerAccount
    {
        $account = $this->accounts->find($context->administration->id(), $this->accountId($id));
        abort_if($account === null, 404);

        return $account;
    }

    private function journalId(string $id): JournalId
    {
        try {
            return new JournalId(new Uuid($id));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    private function accountId(string $id): LedgerAccountId
    {
        try {
            return new LedgerAccountId(new Uuid($id));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    private function context(Request $request): ActiveAdministrationContext
    {
        /** @var ActiveAdministrationContext */
        return $request->attributes->get('administration_context');
    }

    private function base(ActiveAdministrationContext $context): array
    {
        return ['domainUser' => $context->user, 'administrationContext' => $context];
    }
}
