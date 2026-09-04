<?php

declare(strict_types=1);

namespace App\Http\Controllers\Banking;

use App\Application\Banking\AdministrationBankAccountRepository;
use App\Application\Banking\BankEntryDerivedState;
use App\Application\Banking\BankReconciliationCandidateReader;
use App\Application\Banking\BankReconciliationSourceItem;
use App\Application\Banking\BankReconciliationSourceReader;
use App\Application\Banking\BankReconciliationWorklistFilter;
use App\Application\Banking\IgnoreBankStatementEntry;
use App\Application\Banking\ListBankReconciliationWorklist;
use App\Application\Banking\ListEligibleOtherContraAccounts;
use App\Application\Banking\ManualReconciliationStatus;
use App\Application\Banking\PrepareBankReconciliationAllocations;
use App\Application\Banking\ReconcileAndPostBankStatementEntry;
use App\Application\Banking\ReconcileBankStatementEntryStatus;
use App\Application\Banking\RequestedBankAllocation;
use App\Application\Banking\RestoreIgnoredBankStatementEntry;
use App\Application\Identity\PermissionAuthorizer;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Banking\Enums\BankEntryDirection;
use App\Domain\Banking\Enums\BankEntryReconciliationIntent;
use App\Domain\Banking\ValueObjects\AdministrationBankAccountId;
use App\Domain\Banking\ValueObjects\BankStatementEntryId;
use App\Domain\Identity\Definitions\BankingPermission;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Banking\BankEntryReasonRequest;
use App\Http\Requests\Banking\BankReconciliationFilterRequest;
use App\Http\Requests\Banking\ReconcileBankEntryRequest;
use App\Presentation\Banking\BankReconciliationOutcomePresenter;
use App\Presentation\Formatting\DutchMoneyFormatter;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use InvalidArgumentException;

final class BankReconciliationController extends Controller
{
    public function __construct(
        private readonly ListBankReconciliationWorklist $worklist,
        private readonly BankReconciliationSourceReader $sources,
        private readonly BankReconciliationCandidateReader $candidates,
        private readonly AdministrationBankAccountRepository $bankAccounts,
        private readonly ListEligibleOtherContraAccounts $contraAccounts,
        private readonly PrepareBankReconciliationAllocations $prepareAllocations,
        private readonly IgnoreBankStatementEntry $ignoreEntry,
        private readonly RestoreIgnoredBankStatementEntry $restoreEntry,
        private readonly ReconcileAndPostBankStatementEntry $reconcileEntry,
        private readonly PermissionAuthorizer $permissions,
        private readonly DutchMoneyFormatter $money,
    ) {}

    public function index(BankReconciliationFilterRequest $request): View
    {
        $context = $this->context($request);
        $filter = $this->filter($request->validated());
        $all = $this->worklist->execute($context->administration->id(), $filter);
        $page = max(1, (int) ($request->validated('page') ?? 1));
        $items = new LengthAwarePaginator(array_slice($all, ($page - 1) * 50, 50), count($all), 50, $page, ['path' => $request->url(), 'query' => array_diff_key($request->validated(), ['page' => true])]);

        return view('banking.reconciliation.index', $this->viewData($context) + [
            'items' => $items,
            'filters' => $request->validated(),
            'states' => BankEntryDerivedState::cases(),
            'bankAccounts' => $this->bankAccounts->findForAdministration($context->administration->id()),
        ]);
    }

    public function show(Request $request, string $entry): View
    {
        $context = $this->context($request);
        $source = $this->source($context, $entry);
        $candidates = in_array($source->state, [BankEntryDerivedState::Unresolved, BankEntryDerivedState::Reversed], true)
            ? $this->candidates->eligible($context->administration->id(), new PostingDate($source->entry->bookingDate))
            : [];
        $accounts = $this->contraAccounts->execute($context->administration->id());

        return view('banking.reconciliation.show', $this->viewData($context) + compact('source', 'candidates', 'accounts'));
    }

    public function ignore(BankEntryReasonRequest $request, string $entry): RedirectResponse
    {
        $context = $this->context($request);
        $result = $this->ignoreEntry->execute($context->administration->id(), $this->entryId($entry), $request->validated('reason'), $context->user->id());

        return $this->manualResponse($entry, $result->status, 'Bankmutatie genegeerd.');
    }

    public function restore(BankEntryReasonRequest $request, string $entry): RedirectResponse
    {
        $context = $this->context($request);
        $result = $this->restoreEntry->execute($context->administration->id(), $this->entryId($entry), $request->validated('reason'), $context->user->id());

        return $this->manualResponse($entry, $result->status, 'Bankmutatie teruggezet naar Te verwerken.');
    }

    public function post(ReconcileBankEntryRequest $request, string $entry): RedirectResponse
    {
        $context = $this->context($request);
        $data = $request->validated();
        try {
            $entryId = $this->entryId($entry);
            $source = $this->source($context, $entry);
            $intent = BankEntryReconciliationIntent::from($data['intent']);
            $postingDate = new PostingDate(new DateTimeImmutable($data['posting_date']));
            $allocations = $intent === BankEntryReconciliationIntent::Other ? [] : $this->allocations($context, $postingDate, $intent, $data);
            $contra = $intent === BankEntryReconciliationIntent::Other ? new LedgerAccountId(new Uuid($data['contra_ledger_account_id'])) : null;
            $result = $this->reconcileEntry->execute($context->administration->id(), $entryId, $intent, $postingDate, $context->user->id(), $allocations, $contra);
        } catch (InvalidArgumentException) {
            return back()->withInput($request->only($this->roundtripKeys()))->with('error', 'De reconciliation-invoer bevat een ongeldige waarde.');
        }
        if ($result->status === ReconcileBankStatementEntryStatus::NotFound) {
            abort(404);
        }
        if ($result->status !== ReconcileBankStatementEntryStatus::Success) {
            return back()->withInput($request->only($this->roundtripKeys()))->with('error', BankReconciliationOutcomePresenter::message($result->status));
        }

        return redirect()->route('banking.reconciliation.show', $entry)->with('status', 'Bankmutatie gereconciled en atomair geboekt.');
    }

    private function allocations(ActiveAdministrationContext $context, PostingDate $date, BankEntryReconciliationIntent $intent, array $data): array
    {
        $relationId = new RelationId(new Uuid($data['relation_id']));
        $requested = array_map(fn (array $row): RequestedBankAllocation => new RequestedBankAllocation(new OpenItemId(new Uuid($row['open_item_id'])), new Money($row['amount'], new Currency('EUR'))), $data['allocations']);

        return $this->prepareAllocations->execute($context->administration->id(), $date, $intent, $relationId, $requested);
    }

    private function source(ActiveAdministrationContext $context, string $entry): BankReconciliationSourceItem
    {
        $id = $this->entryId($entry);
        $items = $this->sources->list($context->administration->id(), new BankReconciliationWorklistFilter(states: BankEntryDerivedState::cases()));
        foreach ($items as $item) {
            if ($item->entry->id->equals($id)) {
                return $item;
            }
        }
        abort(404);
    }

    private function filter(array $data): BankReconciliationWorklistFilter
    {
        return new BankReconciliationWorklistFilter(
            isset($data['bank_account_id']) ? new AdministrationBankAccountId(new Uuid($data['bank_account_id'])) : null,
            isset($data['from']) ? new DateTimeImmutable($data['from']) : null,
            isset($data['to']) ? new DateTimeImmutable($data['to']) : null,
            isset($data['direction']) ? BankEntryDirection::from($data['direction']) : null,
            isset($data['state']) ? [BankEntryDerivedState::from($data['state'])] : [BankEntryDerivedState::Unresolved],
            isset($data['amount']) ? new Money($data['amount'], new Currency('EUR')) : null,
            $data['search'] ?? null,
        );
    }

    private function manualResponse(string $entry, ManualReconciliationStatus $status, string $success): RedirectResponse
    {
        if ($status === ManualReconciliationStatus::NotFound) {
            abort(404);
        }
        if ($status === ManualReconciliationStatus::Success) {
            return redirect()->route('banking.reconciliation.show', $entry)->with('status', $success);
        }

        return back()->withInput(request()->only('reason'))->with('error', match ($status) {
            ManualReconciliationStatus::AlreadyIgnored => 'Deze bankmutatie is al genegeerd.',
            ManualReconciliationStatus::NotIgnored => 'Deze bankmutatie is niet genegeerd.',
            ManualReconciliationStatus::AlreadyReconciled => 'Deze bankmutatie is al financieel gereconciled.',
            ManualReconciliationStatus::ConcurrencyConflict => 'De bankmutatie is gelijktijdig gewijzigd.',
            default => 'De handmatige status kon wegens een integriteitsfout niet worden gewijzigd.',
        });
    }

    private function viewData(ActiveAdministrationContext $context): array
    {
        return [
            'domainUser' => $context->user,
            'administrationContext' => $context,
            'canReconcile' => $this->permissions->allows($context->permissionIds, BankingPermission::Reconcile->id()),
            'canPost' => $this->permissions->allows($context->permissionIds, BankingPermission::ImportPost->id()),
            'canReverse' => $this->permissions->allows($context->permissionIds, BankingPermission::ReversePayments->id()),
            'moneyFormatter' => $this->money,
        ];
    }

    private function context(Request $request): ActiveAdministrationContext
    {
        return $request->attributes->get('administration_context');
    }

    private function entryId(string $value): BankStatementEntryId
    {
        try {
            return new BankStatementEntryId(new Uuid($value));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    private function roundtripKeys(): array
    {
        return ['intent', 'posting_date', 'relation_id', 'allocations', 'contra_ledger_account_id', 'description'];
    }
}
