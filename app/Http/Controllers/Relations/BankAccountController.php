<?php

declare(strict_types=1);

namespace App\Http\Controllers\Relations;

use App\Application\Identity\PermissionAuthorizer;
use App\Application\Relations\ActivateBankAccount;
use App\Application\Relations\BankAccountDetail;
use App\Application\Relations\BankAccountReadRepository;
use App\Application\Relations\BankAccountWriteResult;
use App\Application\Relations\CreateBankAccount;
use App\Application\Relations\DeactivateBankAccount;
use App\Application\Relations\GetRelationDetail;
use App\Application\Relations\RelationDetail;
use App\Application\Relations\UpdateBankAccount;
use App\Domain\Identity\Definitions\RelationsPermission;
use App\Domain\Relations\ValueObjects\AccountName;
use App\Domain\Relations\ValueObjects\BankAccountId;
use App\Domain\Relations\ValueObjects\Bic;
use App\Domain\Relations\ValueObjects\Iban;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Requests\Relations\StoreBankAccountRequest;
use App\Http\Requests\Relations\UpdateBankAccountRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;

final readonly class BankAccountController
{
    public function __construct(
        private GetRelationDetail $relations,
        private BankAccountReadRepository $bankAccounts,
        private CreateBankAccount $createBankAccount,
        private UpdateBankAccount $updateBankAccount,
        private ActivateBankAccount $activateBankAccount,
        private DeactivateBankAccount $deactivateBankAccount,
        private PermissionAuthorizer $permissionAuthorizer,
    ) {}

    public function show(Request $request, string $relation, string $bankAccount): View
    {
        [$context, $relationId, $relationDetail] = $this->relationContext($request, $relation);

        return view('relations.bank-accounts.show', $this->viewData($context, $relationDetail, $this->bankAccountDetail($context, $relationId, $bankAccount)));
    }

    public function create(Request $request, string $relation): View
    {
        [$context, , $relationDetail] = $this->relationContext($request, $relation);

        return view('relations.bank-accounts.create', $this->viewData($context, $relationDetail));
    }

    public function store(StoreBankAccountRequest $request, string $relation): RedirectResponse
    {
        [$context, $relationId] = $this->relationContext($request, $relation);
        $data = $request->validated();
        try {
            $result = $this->createBankAccount->execute($context->administration->id(), $relationId, new BankAccountId(new Uuid(Str::uuid()->toString())), new Iban($data['iban']), new Bic($data['bic']), new AccountName($data['account_name']));
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['iban' => 'De bankrekeninggegevens zijn ongeldig.']);
        }

        return $this->response($context, $relationId, $result, 'Bankrekening toegevoegd.');
    }

    public function edit(Request $request, string $relation, string $bankAccount): View
    {
        [$context, $relationId, $relationDetail] = $this->relationContext($request, $relation);

        return view('relations.bank-accounts.edit', $this->viewData($context, $relationDetail, $this->bankAccountDetail($context, $relationId, $bankAccount)));
    }

    public function update(UpdateBankAccountRequest $request, string $relation, string $bankAccount): RedirectResponse
    {
        [$context, $relationId] = $this->relationContext($request, $relation);
        try {
            $result = $this->updateBankAccount->execute($context->administration->id(), $relationId, $this->bankAccountId($bankAccount), new AccountName($request->validated('account_name')));
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['account_name' => 'De rekeningnaam is ongeldig.']);
        }

        return $this->response($context, $relationId, $result, 'Bankrekening bijgewerkt.');
    }

    public function activate(Request $request, string $relation, string $bankAccount): RedirectResponse
    {
        [$context, $relationId] = $this->relationContext($request, $relation);

        return $this->response($context, $relationId, $this->activateBankAccount->execute($context->administration->id(), $relationId, $this->bankAccountId($bankAccount)), 'Bankrekening geactiveerd.');
    }

    public function deactivate(Request $request, string $relation, string $bankAccount): RedirectResponse
    {
        [$context, $relationId] = $this->relationContext($request, $relation);

        return $this->response($context, $relationId, $this->deactivateBankAccount->execute($context->administration->id(), $relationId, $this->bankAccountId($bankAccount)), 'Bankrekening gedeactiveerd.');
    }

    /** @return array{ActiveAdministrationContext, RelationId, RelationDetail} */
    private function relationContext(Request $request, string $relation): array
    {
        /** @var ActiveAdministrationContext $context */
        $context = $request->attributes->get('administration_context');
        $relationId = $this->relationId($relation);
        $detail = $this->relations->execute($context->administration->id(), $relationId);
        abort_if($detail === null, 404);

        return [$context, $relationId, $detail];
    }

    private function bankAccountDetail(ActiveAdministrationContext $context, RelationId $relationId, string $value): BankAccountDetail
    {
        $detail = $this->bankAccounts->findForRelation($context->administration->id(), $relationId, $this->bankAccountId($value));
        abort_if($detail === null, 404);

        return $detail;
    }

    private function relationId(string $value): RelationId
    {
        try {
            return new RelationId(new Uuid($value));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    private function bankAccountId(string $value): BankAccountId
    {
        try {
            return new BankAccountId(new Uuid($value));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    private function response(ActiveAdministrationContext $context, RelationId $relationId, BankAccountWriteResult $result, string $message): RedirectResponse
    {
        abort_if($result === BankAccountWriteResult::NotFound, 404);
        if ($result !== BankAccountWriteResult::Success) {
            return back()->withInput()->withErrors(['account_name' => 'De bankrekening kon niet worden opgeslagen. Probeer het opnieuw.']);
        }

        return $this->canView($context) ? redirect()->route('relations.show', $relationId->toString())->with('status', $message) : redirect()->route('app')->with('status', $message);
    }

    /** @return array<string, mixed> */
    private function viewData(ActiveAdministrationContext $context, RelationDetail $relation, ?BankAccountDetail $bankAccount = null): array
    {
        return ['domainUser' => $context->user, 'administrationContext' => $context, 'relation' => $relation, 'bankAccount' => $bankAccount, 'canViewRelations' => $this->canView($context), 'canUpdateRelations' => $this->permissionAuthorizer->allows($context->permissionIds, RelationsPermission::Update->id())];
    }

    private function canView(ActiveAdministrationContext $context): bool
    {
        return $this->permissionAuthorizer->allows($context->permissionIds, RelationsPermission::View->id());
    }
}
