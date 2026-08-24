<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Application\Fiscal\TaxCodeReadRepository;
use App\Application\Identity\PermissionAuthorizer;
use App\Application\Relations\AddressReadRepository;
use App\Application\Relations\CustomerReadRepository;
use App\Application\Relations\RelationReadRepository;
use App\Application\Sales\CreateSalesInvoice;
use App\Application\Sales\SalesInvoiceDetailReadRepository;
use App\Application\Sales\SalesInvoiceListQuery;
use App\Application\Sales\SalesInvoiceListReadRepository;
use App\Application\Sales\SalesInvoiceSortDirection;
use App\Application\Sales\SalesInvoiceSortField;
use App\Application\Sales\SalesInvoiceWriteResult;
use App\Application\Sales\UpdateSalesInvoice;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Identity\Definitions\RelationsPermission;
use App\Domain\Identity\Definitions\SalesPermission;
use App\Domain\Relations\Enums\AddressType;
use App\Domain\Relations\ValueObjects\AddressId;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Sales\Enums\SalesInvoiceStatus;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreSalesInvoiceRequest;
use App\Http\Requests\Sales\UpdateSalesInvoiceRequest;
use App\Presentation\Formatting\DutchMoneyFormatter;
use App\Presentation\Sales\SalesInvoiceStatusPresenter;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

final class SalesInvoiceController extends Controller
{
    public function __construct(
        private readonly SalesInvoiceListReadRepository $list,
        private readonly SalesInvoiceDetailReadRepository $details,
        private readonly CustomerReadRepository $customers,
        private readonly RelationReadRepository $relations,
        private readonly AddressReadRepository $addresses,
        private readonly TaxCodeReadRepository $taxCodes,
        private readonly CreateSalesInvoice $createInvoice,
        private readonly UpdateSalesInvoice $updateInvoice,
        private readonly PermissionAuthorizer $permissions,
        private readonly DutchMoneyFormatter $money,
    ) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(SalesInvoiceStatus::class)],
            'customer' => ['nullable', 'uuid'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'sort' => ['nullable', Rule::in(array_keys($this->sortFields()))],
            'direction' => ['nullable', Rule::enum(SalesInvoiceSortDirection::class)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])],
        ]);
        $context = $this->context($request);
        $query = new SalesInvoiceListQuery(
            $context->administration->id(),
            $validated['q'] ?? null,
            isset($validated['status']) ? SalesInvoiceStatus::from($validated['status']) : null,
            isset($validated['customer']) ? new CustomerId(new Uuid($validated['customer'])) : null,
            isset($validated['date_from']) ? new DateTimeImmutable($validated['date_from']) : null,
            isset($validated['date_to']) ? new DateTimeImmutable($validated['date_to']) : null,
            $this->sortFields()[$validated['sort'] ?? 'invoice_date'],
            SalesInvoiceSortDirection::from($validated['direction'] ?? 'desc'),
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 25),
        );

        return view('sales.invoices.index', $this->viewData($context) + [
            'invoices' => $this->list->search($query),
            'query' => $query,
            'queryParameters' => array_filter($request->only(['q', 'status', 'customer', 'date_from', 'date_to', 'sort', 'direction', 'per_page']), static fn (mixed $value): bool => $value !== null && $value !== ''),
            'customers' => $this->customerOptions($context),
            'statuses' => SalesInvoiceStatus::cases(),
            'hasActiveFilters' => collect($validated)->except(['page', 'per_page'])->filter(static fn (mixed $value): bool => $value !== null && $value !== '')->isNotEmpty(),
        ]);
    }

    public function show(Request $request, string $invoice): View
    {
        $context = $this->context($request);
        $detail = $this->details->find($context->administration->id(), $this->invoiceId($invoice));
        abort_if($detail === null, 404);

        return view('sales.invoices.show', $this->viewData($context) + [
            'invoice' => $detail,
            'taxCodes' => $this->taxCodes->findActiveForAdministrationAndDirection($context->administration->id(), TaxPostingDirection::Output),
        ]);
    }

    public function create(Request $request): View
    {
        $context = $this->context($request);

        return view('sales.invoices.create', $this->viewData($context) + ['customers' => $this->customerOptions($context)]);
    }

    public function store(StoreSalesInvoiceRequest $request): RedirectResponse
    {
        $context = $this->context($request);
        $validated = $request->validated();
        $customerId = new CustomerId(new Uuid($validated['customer_id']));
        $addressId = $this->invoiceAddress($context, $customerId);
        if ($addressId === null) {
            return back()->withInput()->withErrors(['customer_id' => 'De geselecteerde klant heeft niet exact één actief factuuradres.']);
        }
        $id = new SalesInvoiceId(new Uuid(Str::uuid()->toString()));
        $result = $this->createInvoice->execute($context->administration->id(), $id, $customerId, $addressId, new DateTimeImmutable($validated['invoice_date']), new DateTimeImmutable($validated['due_date']));
        if ($result !== SalesInvoiceWriteResult::Success) {
            $message = match ($result) {
                SalesInvoiceWriteResult::CustomerNotFound, SalesInvoiceWriteResult::InactiveCustomer => 'De geselecteerde klant is niet beschikbaar.',
                SalesInvoiceWriteResult::MissingInvoiceAddress => 'De geselecteerde klant heeft geen beschikbaar factuuradres.',
                SalesInvoiceWriteResult::SequenceMissing, SalesInvoiceWriteResult::SequenceInactive => 'Factuurnummering is niet beschikbaar.',
                default => 'De verkoopfactuur kon niet worden aangemaakt.',
            };

            return back()->withInput()->withErrors(['customer_id' => $message]);
        }

        return $this->can($context, SalesPermission::View)
            ? redirect()->route('sales.invoices.show', $id->toString())->with('status', 'Verkoopfactuur aangemaakt.')
            : redirect()->route('app')->with('status', 'Verkoopfactuur aangemaakt.');
    }

    public function edit(Request $request, string $invoice): View
    {
        $context = $this->context($request);
        $detail = $this->details->find($context->administration->id(), $this->invoiceId($invoice));
        abort_if($detail === null, 404);
        abort_if($detail->status() !== SalesInvoiceStatus::Draft, 409);

        return view('sales.invoices.edit', $this->viewData($context) + ['invoice' => $detail]);
    }

    public function update(UpdateSalesInvoiceRequest $request, string $invoice): RedirectResponse
    {
        $context = $this->context($request);
        $id = $this->invoiceId($invoice);
        $validated = $request->validated();
        $result = $this->updateInvoice->execute($context->administration->id(), $id, new DateTimeImmutable($validated['invoice_date']), new DateTimeImmutable($validated['due_date']));

        return $this->mutationRedirect($context, $id, $result, 'Verkoopfactuur bijgewerkt.');
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

    private function invoiceAddress(ActiveAdministrationContext $context, CustomerId $customerId): ?AddressId
    {
        $customer = null;
        foreach ($this->customers->findForAdministration($context->administration->id()) as $candidate) {
            if ($candidate->id()->equals($customerId) && $candidate->isActive()) {
                $customer = $candidate;
                break;
            }
        }
        if ($customer === null) {
            return null;
        }
        $matches = array_values(array_filter($this->addresses->listForRelation($context->administration->id(), $customer->relationId()), static fn ($address): bool => $address->active && $address->type === AddressType::Invoice));

        return count($matches) === 1 ? $matches[0]->id : null;
    }

    /** @return array<string, mixed> */
    private function viewData(ActiveAdministrationContext $context): array
    {
        return [
            'domainUser' => $context->user,
            'administrationContext' => $context,
            'canViewRelations' => $this->permissions->allows($context->permissionIds, RelationsPermission::View->id()),
            'canViewSales' => $this->can($context, SalesPermission::View),
            'canManageInvoiceDrafts' => $this->can($context, SalesPermission::ManageInvoiceDrafts),
            'canIssueInvoices' => $this->can($context, SalesPermission::IssueInvoices),
            'statusPresenter' => SalesInvoiceStatusPresenter::class,
            'moneyFormatter' => $this->money,
        ];
    }

    private function context(Request $request): ActiveAdministrationContext
    {
        /** @var ActiveAdministrationContext */
        return $request->attributes->get('administration_context');
    }

    private function invoiceId(string $value): SalesInvoiceId
    {
        try {
            return new SalesInvoiceId(new Uuid($value));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    private function can(ActiveAdministrationContext $context, SalesPermission $permission): bool
    {
        return $this->permissions->allows($context->permissionIds, $permission->id());
    }

    private function mutationRedirect(ActiveAdministrationContext $context, SalesInvoiceId $id, SalesInvoiceWriteResult $result, string $message): RedirectResponse
    {
        if ($result === SalesInvoiceWriteResult::NotFound) {
            abort(404);
        }
        if ($result !== SalesInvoiceWriteResult::Success) {
            return back()->with('error', 'De actie is niet toegestaan of kan niet exact worden verwerkt.');
        }

        return $this->can($context, SalesPermission::View)
            ? redirect()->route('sales.invoices.show', $id->toString())->with('status', $message)
            : redirect()->route('app')->with('status', $message);
    }

    /** @return array<string, SalesInvoiceSortField> */
    private function sortFields(): array
    {
        return ['number' => SalesInvoiceSortField::Number, 'customer_name' => SalesInvoiceSortField::CustomerName, 'invoice_date' => SalesInvoiceSortField::InvoiceDate, 'due_date' => SalesInvoiceSortField::DueDate, 'status' => SalesInvoiceSortField::Status];
    }
}
