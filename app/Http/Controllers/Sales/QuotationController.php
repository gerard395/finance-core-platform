<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Application\Identity\PermissionAuthorizer;
use App\Application\Relations\CustomerReadRepository;
use App\Application\Relations\RelationReadRepository;
use App\Application\Sales\CreateQuotation;
use App\Application\Sales\QuotationDetailReadRepository;
use App\Application\Sales\QuotationListQuery;
use App\Application\Sales\QuotationListReadRepository;
use App\Application\Sales\QuotationSortDirection;
use App\Application\Sales\QuotationSortField;
use App\Application\Sales\QuotationWriteResult;
use App\Application\Sales\UpdateQuotation;
use App\Domain\Identity\Definitions\RelationsPermission;
use App\Domain\Identity\Definitions\SalesPermission;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Sales\Enums\QuotationStatus;
use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreQuotationRequest;
use App\Http\Requests\Sales\UpdateQuotationRequest;
use App\Presentation\Sales\QuotationStatusPresenter;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

final class QuotationController extends Controller
{
    public function __construct(
        private readonly QuotationListReadRepository $list,
        private readonly QuotationDetailReadRepository $details,
        private readonly CustomerReadRepository $customers,
        private readonly RelationReadRepository $relations,
        private readonly CreateQuotation $createQuotation,
        private readonly UpdateQuotation $updateQuotation,
        private readonly PermissionAuthorizer $permissions,
    ) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(QuotationStatus::class)],
            'customer' => ['nullable', 'uuid'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'sort' => ['nullable', Rule::in(array_keys($this->sortFields()))],
            'direction' => ['nullable', Rule::enum(QuotationSortDirection::class)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])],
        ]);
        $context = $this->context($request);
        $query = new QuotationListQuery(
            $context->administration->id(),
            $validated['q'] ?? null,
            isset($validated['status']) ? QuotationStatus::from($validated['status']) : null,
            isset($validated['customer']) ? new CustomerId(new Uuid($validated['customer'])) : null,
            isset($validated['date_from']) ? new DateTimeImmutable($validated['date_from']) : null,
            isset($validated['date_to']) ? new DateTimeImmutable($validated['date_to']) : null,
            $this->sortFields()[$validated['sort'] ?? 'quotation_date'],
            QuotationSortDirection::from($validated['direction'] ?? 'desc'),
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 25),
        );

        return view('sales.quotations.index', $this->viewData($context) + [
            'quotations' => $this->list->search($query),
            'query' => $query,
            'queryParameters' => array_filter($request->only(['q', 'status', 'customer', 'date_from', 'date_to', 'sort', 'direction', 'per_page']), static fn (mixed $value): bool => $value !== null && $value !== ''),
            'customers' => $this->customerOptions($context),
            'statusPresenter' => QuotationStatusPresenter::class,
            'statuses' => QuotationStatus::cases(),
            'hasActiveFilters' => collect($validated)->except(['page', 'per_page'])->filter(static fn (mixed $value): bool => $value !== null && $value !== '')->isNotEmpty(),
        ]);
    }

    public function show(Request $request, string $quotation): View
    {
        $context = $this->context($request);
        $detail = $this->details->find($context->administration->id(), $this->quotationId($quotation));
        abort_if($detail === null, 404);

        return view('sales.quotations.show', $this->viewData($context) + [
            'quotation' => $detail,
            'statusPresenter' => QuotationStatusPresenter::class,
        ]);
    }

    public function create(Request $request): View
    {
        $context = $this->context($request);

        return view('sales.quotations.create', $this->viewData($context) + ['customers' => $this->customerOptions($context)]);
    }

    public function store(StoreQuotationRequest $request): RedirectResponse
    {
        $context = $this->context($request);
        $validated = $request->validated();
        $id = new QuotationId(new Uuid(Str::uuid()->toString()));
        $result = $this->createQuotation->execute(
            $context->administration->id(),
            $id,
            new CustomerId(new Uuid($validated['customer_id'])),
            $context->administration->baseCurrency(),
            new DateTimeImmutable($validated['quotation_date']),
            isset($validated['expiry_date']) ? new DateTimeImmutable($validated['expiry_date']) : null,
        );

        if ($result !== QuotationWriteResult::Success) {
            $message = match ($result) {
                QuotationWriteResult::CustomerNotFound, QuotationWriteResult::InactiveCustomer => 'De geselecteerde klant is niet beschikbaar.',
                QuotationWriteResult::SequenceMissing, QuotationWriteResult::SequenceInactive => 'Offertenummering is niet beschikbaar.',
                default => 'De offerte kon niet worden aangemaakt.',
            };

            return back()->withInput()->withErrors(['customer_id' => $message]);
        }

        return $this->can($context, SalesPermission::View)
            ? redirect()->route('sales.quotations.show', $id->toString())->with('status', 'Offerte aangemaakt.')
            : redirect()->route('app')->with('status', 'Offerte aangemaakt.');
    }

    public function edit(Request $request, string $quotation): View
    {
        $context = $this->context($request);
        $detail = $this->details->find($context->administration->id(), $this->quotationId($quotation));
        abort_if($detail === null, 404);
        abort_if($detail->status() !== QuotationStatus::Draft, 409);

        return view('sales.quotations.edit', $this->viewData($context) + ['quotation' => $detail]);
    }

    public function update(UpdateQuotationRequest $request, string $quotation): RedirectResponse
    {
        $context = $this->context($request);
        $id = $this->quotationId($quotation);
        $validated = $request->validated();
        $result = $this->updateQuotation->execute(
            $context->administration->id(),
            $id,
            new DateTimeImmutable($validated['quotation_date']),
            isset($validated['expiry_date']) ? new DateTimeImmutable($validated['expiry_date']) : null,
        );

        return $this->mutationRedirect($context, $id, $result, 'Offerte bijgewerkt.');
    }

    private function quotationId(string $value): QuotationId
    {
        try {
            return new QuotationId(new Uuid($value));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    /** @return list<array{id: string, number: string, name: string}> */
    private function customerOptions(ActiveAdministrationContext $context): array
    {
        $relations = [];
        foreach ($this->relations->findForAdministration($context->administration->id()) as $relation) {
            $relations[$relation->id()->toString()] = $relation;
        }
        $options = [];
        foreach ($this->customers->findForAdministration($context->administration->id()) as $customer) {
            $relation = $relations[$customer->relationId()->toString()] ?? null;
            if ($customer->isActive() && $relation !== null) {
                $options[] = ['id' => $customer->id()->toString(), 'number' => $customer->customerNumber()->toString(), 'name' => $relation->displayName()->toString()];
            }
        }

        return $options;
    }

    /** @return array<string, mixed> */
    private function viewData(ActiveAdministrationContext $context): array
    {
        return [
            'domainUser' => $context->user,
            'administrationContext' => $context,
            'canViewRelations' => $this->permissions->allows($context->permissionIds, RelationsPermission::View->id()),
            'canViewSales' => $this->can($context, SalesPermission::View),
            'canManageQuotations' => $this->can($context, SalesPermission::ManageQuotations),
        ];
    }

    private function context(Request $request): ActiveAdministrationContext
    {
        /** @var ActiveAdministrationContext */
        return $request->attributes->get('administration_context');
    }

    private function can(ActiveAdministrationContext $context, SalesPermission $permission): bool
    {
        return $this->permissions->allows($context->permissionIds, $permission->id());
    }

    private function mutationRedirect(ActiveAdministrationContext $context, QuotationId $id, QuotationWriteResult $result, string $message): RedirectResponse
    {
        if ($result === QuotationWriteResult::NotFound) {
            abort(404);
        }
        if ($result !== QuotationWriteResult::Success) {
            return back()->with('error', 'De actie is niet toegestaan in de huidige offertestatus.');
        }

        return $this->can($context, SalesPermission::View)
            ? redirect()->route('sales.quotations.show', $id->toString())->with('status', $message)
            : redirect()->route('app')->with('status', $message);
    }

    /** @return array<string, QuotationSortField> */
    private function sortFields(): array
    {
        return ['number' => QuotationSortField::Number, 'customer_name' => QuotationSortField::CustomerName, 'quotation_date' => QuotationSortField::QuotationDate, 'expiry_date' => QuotationSortField::ExpiryDate, 'status' => QuotationSortField::Status];
    }
}
