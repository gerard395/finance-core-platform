<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Application\Identity\PermissionAuthorizer;
use App\Application\Sales\CreateSalesCreditInvoiceFromInvoice;
use App\Application\Sales\EligibleSalesCreditSourceQuery;
use App\Application\Sales\EligibleSalesCreditSourceReadRepository;
use App\Application\Sales\SalesCreditInvoiceDetailReadRepository;
use App\Application\Sales\SalesCreditInvoiceListQuery;
use App\Application\Sales\SalesCreditInvoiceListReadRepository;
use App\Application\Sales\SalesCreditInvoiceSortField;
use App\Application\Sales\SalesCreditInvoiceWriteResult;
use App\Application\Sales\SalesInvoiceSortDirection;
use App\Domain\Identity\Definitions\RelationsPermission;
use App\Domain\Identity\Definitions\SalesPermission;
use App\Domain\Sales\Enums\SalesCreditInvoiceStatus;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreSalesCreditInvoiceRequest;
use App\Presentation\Formatting\DutchMoneyFormatter;
use App\Presentation\Sales\SalesCreditInvoiceStatusPresenter;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

final class SalesCreditInvoiceController extends Controller
{
    public function __construct(private readonly SalesCreditInvoiceListReadRepository $list, private readonly SalesCreditInvoiceDetailReadRepository $details, private readonly EligibleSalesCreditSourceReadRepository $eligibleSources, private readonly CreateSalesCreditInvoiceFromInvoice $createCredit, private readonly PermissionAuthorizer $permissions, private readonly DutchMoneyFormatter $money) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', Rule::enum(SalesCreditInvoiceStatus::class)],
            'date_from' => ['nullable', 'date_format:Y-m-d'], 'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'sort' => ['nullable', Rule::in(array_keys($this->sortFields()))], 'direction' => ['nullable', Rule::enum(SalesInvoiceSortDirection::class)],
            'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])],
        ]);
        $context = $this->context($request);
        $query = new SalesCreditInvoiceListQuery($context->administration->id(), $validated['q'] ?? null, isset($validated['status']) ? SalesCreditInvoiceStatus::from($validated['status']) : null, dateFrom: isset($validated['date_from']) ? new DateTimeImmutable($validated['date_from']) : null, dateTo: isset($validated['date_to']) ? new DateTimeImmutable($validated['date_to']) : null, sortField: $this->sortFields()[$validated['sort'] ?? 'credit_date'], sortDirection: SalesInvoiceSortDirection::from($validated['direction'] ?? 'desc'), page: (int) ($validated['page'] ?? 1), perPage: (int) ($validated['per_page'] ?? 25));

        return view('sales.credit-invoices.index', $this->viewData($context) + [
            'credits' => $this->list->search($query), 'statuses' => SalesCreditInvoiceStatus::cases(),
            'queryParameters' => array_filter($request->only(['q', 'status', 'date_from', 'date_to', 'sort', 'direction', 'per_page']), static fn ($value) => $value !== null && $value !== ''),
            'hasActiveFilters' => collect($validated)->except(['page', 'per_page'])->filter(static fn ($value) => $value !== null && $value !== '')->isNotEmpty(),
        ]);
    }

    public function create(Request $request): View
    {
        $validated = $request->validate(['source_q' => ['nullable', 'string', 'max:255'], 'source_page' => ['nullable', 'integer', 'min:1']]);
        $context = $this->context($request);
        $sources = $this->eligibleSources->listEligible(new EligibleSalesCreditSourceQuery($context->administration->id(), $validated['source_q'] ?? null, page: (int) ($validated['source_page'] ?? 1)));

        return view('sales.credit-invoices.create', $this->viewData($context) + ['sources' => $sources, 'sourceQuery' => $validated['source_q'] ?? null]);
    }

    public function store(StoreSalesCreditInvoiceRequest $request): RedirectResponse
    {
        $context = $this->context($request);
        $validated = $request->validated();
        $result = $this->createCredit->execute($context->administration->id(), new SalesInvoiceId(new Uuid($validated['source_invoice_id'])), new DateTimeImmutable($validated['credit_date']));
        if ($result->status() === SalesCreditInvoiceWriteResult::NotFound) {
            abort(404);
        }
        if ($result->status() !== SalesCreditInvoiceWriteResult::Success) {
            $message = match ($result->status()) {
                SalesCreditInvoiceWriteResult::SourceNotPosted, SalesCreditInvoiceWriteResult::AlreadyCredited => 'Deze factuur kan niet worden gecrediteerd.',
                SalesCreditInvoiceWriteResult::FinancialPostingMissing, SalesCreditInvoiceWriteResult::ReversalSourceMissing, SalesCreditInvoiceWriteResult::ReversalSourceInvalid => 'De factuur heeft geen consistente financiële broninformatie.',
                SalesCreditInvoiceWriteResult::SequenceMissing, SalesCreditInvoiceWriteResult::SequenceInactive => 'Creditfactuurnummering is niet beschikbaar.',
                default => 'De creditfactuur kon niet worden aangemaakt.',
            };

            return back()->withInput()->withErrors(['source_invoice_id' => $message]);
        }
        $id = $result->creditInvoiceId();
        if ($id === null) {
            return back()->withInput()->withErrors(['source_invoice_id' => 'De creditfactuur kon niet worden aangemaakt.']);
        }

        return $this->can($context, SalesPermission::View) ? redirect()->route('sales.credit-invoices.show', $id->toString())->with('status', 'Creditfactuur aangemaakt.') : redirect()->route('app')->with('status', 'Creditfactuur aangemaakt.');
    }

    public function show(Request $request, string $creditInvoice): View
    {
        $context = $this->context($request);
        $detail = $this->details->find($context->administration->id(), $this->creditId($creditInvoice));
        abort_if($detail === null, 404);

        return view('sales.credit-invoices.show', $this->viewData($context) + ['detail' => $detail]);
    }

    /** @return array<string, mixed> */
    private function viewData(ActiveAdministrationContext $context): array
    {
        return ['domainUser' => $context->user, 'administrationContext' => $context, 'canViewRelations' => $this->canRelations($context), 'canViewSales' => $this->can($context, SalesPermission::View), 'canManageCreditDrafts' => $this->can($context, SalesPermission::ManageCreditInvoiceDrafts), 'canIssueCredits' => $this->can($context, SalesPermission::IssueCreditInvoices), 'statusPresenter' => SalesCreditInvoiceStatusPresenter::class, 'moneyFormatter' => $this->money];
    }

    private function context(Request $request): ActiveAdministrationContext
    {
        return $request->attributes->get('administration_context');
    }

    private function can(ActiveAdministrationContext $context, SalesPermission $permission): bool
    {
        return $this->permissions->allows($context->permissionIds, $permission->id());
    }

    private function canRelations(ActiveAdministrationContext $context): bool
    {
        return $this->permissions->allows($context->permissionIds, RelationsPermission::View->id());
    }

    private function creditId(string $value): SalesCreditInvoiceId
    {
        try {
            return new SalesCreditInvoiceId(new Uuid($value));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    /** @return array<string, SalesCreditInvoiceSortField> */
    private function sortFields(): array
    {
        return ['number' => SalesCreditInvoiceSortField::Number, 'customer_name' => SalesCreditInvoiceSortField::CustomerName, 'credit_date' => SalesCreditInvoiceSortField::CreditDate, 'status' => SalesCreditInvoiceSortField::Status];
    }
}
