<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Accounting\AccountingPeriodHistoryReadRepository;
use App\Application\Accounting\AccountingPeriodMutationStatus;
use App\Application\Accounting\AccountingPeriodPlanReplacementStatus;
use App\Application\Accounting\BookYearRepository;
use App\Application\Accounting\CloseAccountingPeriod;
use App\Application\Accounting\CreateAccountingPeriod;
use App\Application\Accounting\CreateBookYear;
use App\Application\Accounting\GetAccountingPeriodReadiness;
use App\Application\Accounting\ReopenAccountingPeriod;
use App\Application\Accounting\ReplaceAccountingPeriodPlan;
use App\Application\Accounting\UpdateBookYearLabel;
use App\Application\Identity\PermissionAuthorizer;
use App\Domain\Accounting\Entities\AccountingPeriod;
use App\Domain\Accounting\Entities\BookYear;
use App\Domain\Accounting\ValueObjects\AccountingPeriodId;
use App\Domain\Accounting\ValueObjects\BookYearId;
use App\Domain\Identity\Definitions\AccountingPeriodPermission;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Requests\Accounting\ReplaceAccountingPeriodPlanRequest;
use App\Http\Requests\Accounting\StoreAccountingPeriodRequest;
use App\Http\Requests\Accounting\StoreBookYearRequest;
use App\Http\Requests\Accounting\TransitionAccountingPeriodRequest;
use App\Http\Requests\Accounting\UpdateBookYearLabelRequest;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;

final readonly class AccountingPeriodController
{
    public function __construct(
        private BookYearRepository $years,
        private AccountingPeriodHistoryReadRepository $history,
        private GetAccountingPeriodReadiness $readiness,
        private CreateBookYear $createYear,
        private UpdateBookYearLabel $updateLabel,
        private CreateAccountingPeriod $createPeriod,
        private CloseAccountingPeriod $closePeriod,
        private ReopenAccountingPeriod $reopenPeriod,
        private ReplaceAccountingPeriodPlan $replacePlan,
        private PermissionAuthorizer $permissions,
    ) {}

    public function index(Request $request): View
    {
        $context = $this->context($request);

        return view('settings.accounting-periods.index', $this->base($context) + [
            'years' => $this->years->allForAdministration($context->administration->id()),
            'readiness' => $this->readiness->forAdministration($context->administration->id()),
            'canManage' => $this->can($context, AccountingPeriodPermission::Manage),
        ]);
    }

    public function create(Request $request): View
    {
        return view('settings.accounting-periods.create', $this->base($this->context($request)));
    }

    public function store(StoreBookYearRequest $request): RedirectResponse
    {
        $context = $this->context($request);
        $data = $request->validated();
        try {
            $year = new BookYear(
                new BookYearId(new Uuid((string) Str::uuid())),
                $context->administration->id(),
                $data['code'],
                $data['label'] ?? '',
                new DateTimeImmutable($data['start_date']),
                new DateTimeImmutable($data['end_date']),
            );
        } catch (DomainException|InvalidArgumentException) {
            return back()->withInput()->withErrors(['book_year' => 'Het boekjaar bevat ongeldige gegevens.']);
        }

        return $this->mutation($this->createYear->execute($year), 'settings.accounting-periods.show', [$year->id()->toString()], 'Boekjaar aangemaakt.', 'Het boekjaar overlapt met een bestaand boekjaar of de code bestaat al.', 'book_year');
    }

    public function show(Request $request, string $bookYear): View
    {
        $context = $this->context($request);
        $year = $this->year($context, $bookYear);
        $histories = [];
        foreach ($year->periods() as $period) {
            $histories[$period->id()->toString()] = $this->history->get($context->administration->id(), $period->id());
        }

        return view('settings.accounting-periods.show', $this->base($context) + [
            'year' => $year,
            'histories' => $histories,
            'readiness' => $this->readiness->forAdministration($context->administration->id()),
            'canManage' => $this->can($context, AccountingPeriodPermission::Manage),
            'canClose' => $this->can($context, AccountingPeriodPermission::Close),
            'canReopen' => $this->can($context, AccountingPeriodPermission::Reopen),
            'canReconfigure' => $this->can($context, AccountingPeriodPermission::Manage)
                && $this->replacePlan->eligibility($context->administration->id(), $year->id()) === AccountingPeriodPlanReplacementStatus::Success,
        ]);
    }

    public function edit(Request $request, string $bookYear): View
    {
        $context = $this->context($request);

        return view('settings.accounting-periods.edit', $this->base($context) + ['year' => $this->year($context, $bookYear)]);
    }

    public function update(UpdateBookYearLabelRequest $request, string $bookYear): RedirectResponse
    {
        $context = $this->context($request);
        $id = $this->bookYearId($bookYear);

        return $this->mutation($this->updateLabel->execute($context->administration->id(), $id, $request->validated()['label'] ?? ''), 'settings.accounting-periods.show', [$id->toString()], 'Label bijgewerkt.');
    }

    public function storePeriod(StoreAccountingPeriodRequest $request, string $bookYear): RedirectResponse
    {
        $context = $this->context($request);
        $year = $this->year($context, $bookYear);
        $data = $request->validated();
        try {
            $period = new AccountingPeriod(
                new AccountingPeriodId(new Uuid((string) Str::uuid())),
                $context->administration->id(),
                $year->id(),
                $data['code'],
                $data['label'],
                new DateTimeImmutable($data['start_date']),
                new DateTimeImmutable($data['end_date']),
            );
        } catch (DomainException|InvalidArgumentException) {
            return back()->withInput()->withErrors(['period' => 'De periode bevat ongeldige gegevens.']);
        }

        return $this->mutation($this->createPeriod->execute($period), 'settings.accounting-periods.show', [$year->id()->toString()], 'Periode toegevoegd.', 'De periode valt buiten het boekjaar, overlapt of gebruikt een bestaande code.');
    }

    public function close(TransitionAccountingPeriodRequest $request, string $bookYear, string $period): RedirectResponse
    {
        return $this->transition($request, $bookYear, $period, false);
    }

    public function reopen(TransitionAccountingPeriodRequest $request, string $bookYear, string $period): RedirectResponse
    {
        return $this->transition($request, $bookYear, $period, true);
    }

    public function replacePlan(ReplaceAccountingPeriodPlanRequest $request, string $bookYear): RedirectResponse
    {
        $context = $this->context($request);
        $id = $this->bookYearId($bookYear);
        $status = $this->replacePlan->withMonthlyPeriods($context->administration->id(), $id, $request->validated()['expected_period_ids']);

        if ($status === AccountingPeriodPlanReplacementStatus::Success) {
            return redirect()->route('settings.accounting-periods.show', $id->toString())->with('status', 'Periodenindeling vervangen door maandperioden.');
        }
        if ($status === AccountingPeriodPlanReplacementStatus::NotFound) {
            abort(404);
        }

        $message = match ($status) {
            AccountingPeriodPlanReplacementStatus::PeriodClosed => 'Een gesloten periode kan niet opnieuw worden ingericht.',
            AccountingPeriodPlanReplacementStatus::HistoryExists => 'Een periodenindeling met audithistorie kan niet worden vervangen.',
            AccountingPeriodPlanReplacementStatus::IncompleteCoverage => 'De vervangende perioden dekken het boekjaar niet volledig.',
            AccountingPeriodPlanReplacementStatus::Overlap => 'De vervangende perioden overlappen.',
            AccountingPeriodPlanReplacementStatus::HistoricalPostingDateUncovered => 'Een historische boekingsdatum wordt niet gedekt.',
            AccountingPeriodPlanReplacementStatus::IntegrityFailure => 'De periodenindeling is intussen gewijzigd of kon niet veilig worden vervangen.',
            AccountingPeriodPlanReplacementStatus::Success, AccountingPeriodPlanReplacementStatus::NotFound => throw new \LogicException,
        };

        return back()->withErrors(['period_plan' => $message]);
    }

    private function transition(TransitionAccountingPeriodRequest $request, string $bookYear, string $period, bool $reopen): RedirectResponse
    {
        $context = $this->context($request);
        $year = $this->year($context, $bookYear);
        $periodId = $this->periodId($period);
        abort_unless(collect($year->periods())->contains(fn (AccountingPeriod $item): bool => $item->id()->equals($periodId)), 404);
        $result = $reopen
            ? $this->reopenPeriod->execute($context->administration->id(), $periodId, $request->validated()['reason'], $context->user->id(), new DateTimeImmutable)
            : $this->closePeriod->execute($context->administration->id(), $periodId, $request->validated()['reason'], $context->user->id(), new DateTimeImmutable);

        return $this->mutation($result, 'settings.accounting-periods.show', [$year->id()->toString()], $reopen ? 'Periode heropend.' : 'Periode gesloten.');
    }

    private function mutation(AccountingPeriodMutationStatus $status, string $route, array $parameters, string $success, string $integrity = 'De wijziging kon niet veilig worden opgeslagen.', string $errorKey = 'period'): RedirectResponse
    {
        return match ($status) {
            AccountingPeriodMutationStatus::Success => redirect()->route($route, $parameters)->with('status', $success),
            AccountingPeriodMutationStatus::NotFound => abort(404),
            AccountingPeriodMutationStatus::InvalidState => back()->withErrors([$errorKey => 'De periode heeft niet de vereiste status of de reden is ongeldig.']),
            AccountingPeriodMutationStatus::IntegrityFailure => back()->withInput()->withErrors([$errorKey => $integrity]),
        };
    }

    private function year(ActiveAdministrationContext $context, string $id): BookYear
    {
        $year = $this->years->find($context->administration->id(), $this->bookYearId($id));
        abort_if($year === null, 404);

        return $year;
    }

    private function bookYearId(string $id): BookYearId
    {
        try {
            return new BookYearId(new Uuid($id));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    private function periodId(string $id): AccountingPeriodId
    {
        try {
            return new AccountingPeriodId(new Uuid($id));
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

    private function can(ActiveAdministrationContext $context, AccountingPeriodPermission $permission): bool
    {
        return $this->permissions->allows($context->permissionIds, $permission->id());
    }
}
