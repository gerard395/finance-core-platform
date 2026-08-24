<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Application\Identity\PermissionAuthorizer;
use App\Application\Relations\CustomerReadRepository;
use App\Application\Relations\RelationReadRepository;
use App\Application\Sales\CreateOrder;
use App\Application\Sales\OrderDetailReadRepository;
use App\Application\Sales\OrderInvoicingProgressReader;
use App\Application\Sales\OrderListQuery;
use App\Application\Sales\OrderListReadRepository;
use App\Application\Sales\OrderSortDirection;
use App\Application\Sales\OrderSortField;
use App\Application\Sales\OrderWriteResult;
use App\Application\Sales\UpdateOrder;
use App\Domain\Identity\Definitions\RelationsPermission;
use App\Domain\Identity\Definitions\SalesPermission;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Sales\Enums\OrderStatus;
use App\Domain\Sales\ValueObjects\OrderId;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreOrderRequest;
use App\Http\Requests\Sales\UpdateOrderRequest;
use App\Presentation\Sales\OrderStatusPresenter;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

final class OrderController extends Controller
{
    public function __construct(
        private readonly OrderListReadRepository $list,
        private readonly OrderDetailReadRepository $details,
        private readonly OrderInvoicingProgressReader $invoicingProgress,
        private readonly CustomerReadRepository $customers,
        private readonly RelationReadRepository $relations,
        private readonly CreateOrder $createOrder,
        private readonly UpdateOrder $updateOrder,
        private readonly PermissionAuthorizer $permissions,
    ) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'customer' => ['nullable', 'uuid'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'sort' => ['nullable', Rule::in(array_keys($this->sortFields()))],
            'direction' => ['nullable', Rule::enum(OrderSortDirection::class)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])],
        ]);
        $context = $this->context($request);
        $query = new OrderListQuery(
            $context->administration->id(),
            $validated['q'] ?? null,
            isset($validated['status']) ? OrderStatus::from($validated['status']) : null,
            isset($validated['customer']) ? new CustomerId(new Uuid($validated['customer'])) : null,
            isset($validated['date_from']) ? new DateTimeImmutable($validated['date_from']) : null,
            isset($validated['date_to']) ? new DateTimeImmutable($validated['date_to']) : null,
            $this->sortFields()[$validated['sort'] ?? 'order_date'],
            OrderSortDirection::from($validated['direction'] ?? 'desc'),
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 25),
        );

        return view('sales.orders.index', $this->viewData($context) + [
            'orders' => $this->list->search($query),
            'query' => $query,
            'queryParameters' => array_filter($request->only(['q', 'status', 'customer', 'date_from', 'date_to', 'sort', 'direction', 'per_page']), static fn (mixed $value): bool => $value !== null && $value !== ''),
            'customers' => $this->customerOptions($context),
            'statusPresenter' => OrderStatusPresenter::class,
            'statuses' => OrderStatus::cases(),
            'hasActiveFilters' => collect($validated)->except(['page', 'per_page'])->filter(static fn (mixed $value): bool => $value !== null && $value !== '')->isNotEmpty(),
        ]);
    }

    public function show(Request $request, string $order): View
    {
        $context = $this->context($request);
        $detail = $this->details->find($context->administration->id(), $this->orderId($order));
        abort_if($detail === null, 404);
        $progress = $this->invoicingProgress->progress($context->administration->id(), $detail->id());
        abort_if($progress === null, 404);
        $progressByLine = [];
        foreach ($progress->lines() as $line) {
            $progressByLine[$line->orderLineId()->toString()] = $line;
        }

        return view('sales.orders.show', $this->viewData($context) + [
            'order' => $detail,
            'progressByLine' => $progressByLine,
            'canCreateInvoice' => $this->can($context, SalesPermission::ManageInvoiceDrafts)
                && in_array($detail->status(), [OrderStatus::Confirmed, OrderStatus::PartiallyInvoiced], true)
                && collect($progress->lines())->contains(static fn ($line): bool => ! $line->available()->isZero()),
            'statusPresenter' => OrderStatusPresenter::class,
        ]);
    }

    public function create(Request $request): View
    {
        $context = $this->context($request);

        return view('sales.orders.create', $this->viewData($context) + ['customers' => $this->customerOptions($context)]);
    }

    public function store(StoreOrderRequest $request): RedirectResponse
    {
        $context = $this->context($request);
        $validated = $request->validated();
        $id = new OrderId(new Uuid(Str::uuid()->toString()));
        $result = $this->createOrder->execute(
            $context->administration->id(),
            $id,
            new CustomerId(new Uuid($validated['customer_id'])),
            $context->administration->baseCurrency(),
            new DateTimeImmutable($validated['order_date']),
            null,
        );

        if ($result !== OrderWriteResult::Success) {
            $message = match ($result) {
                OrderWriteResult::CustomerNotFound, OrderWriteResult::InactiveCustomer => 'De geselecteerde klant is niet beschikbaar.',
                OrderWriteResult::SequenceMissing, OrderWriteResult::SequenceInactive => 'Ordernummering is niet beschikbaar.',
                default => 'De order kon niet worden aangemaakt.',
            };

            return back()->withInput()->withErrors(['customer_id' => $message]);
        }

        return $this->can($context, SalesPermission::View)
            ? redirect()->route('sales.orders.show', $id->toString())->with('status', 'Order aangemaakt.')
            : redirect()->route('app')->with('status', 'Order aangemaakt.');
    }

    public function edit(Request $request, string $order): View
    {
        $context = $this->context($request);
        $detail = $this->details->find($context->administration->id(), $this->orderId($order));
        abort_if($detail === null, 404);
        abort_if($detail->status() !== OrderStatus::Draft, 409);

        return view('sales.orders.edit', $this->viewData($context) + ['order' => $detail]);
    }

    public function update(UpdateOrderRequest $request, string $order): RedirectResponse
    {
        $context = $this->context($request);
        $id = $this->orderId($order);
        $validated = $request->validated();
        $result = $this->updateOrder->execute(
            $context->administration->id(),
            $id,
            new DateTimeImmutable($validated['order_date']),
        );

        return $this->mutationRedirect($context, $id, $result, 'Order bijgewerkt.');
    }

    private function orderId(string $value): OrderId
    {
        try {
            return new OrderId(new Uuid($value));
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
            'canManageOrders' => $this->can($context, SalesPermission::ManageOrders),
            'canManageInvoiceDrafts' => $this->can($context, SalesPermission::ManageInvoiceDrafts),
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

    private function mutationRedirect(ActiveAdministrationContext $context, OrderId $id, OrderWriteResult $result, string $message): RedirectResponse
    {
        if ($result === OrderWriteResult::NotFound) {
            abort(404);
        }
        if ($result !== OrderWriteResult::Success) {
            return back()->with('error', 'De actie is niet toegestaan in de huidige orderstatus.');
        }

        return $this->can($context, SalesPermission::View)
            ? redirect()->route('sales.orders.show', $id->toString())->with('status', $message)
            : redirect()->route('app')->with('status', $message);
    }

    /** @return array<string, OrderSortField> */
    private function sortFields(): array
    {
        return ['number' => OrderSortField::Number, 'customer_name' => OrderSortField::CustomerName, 'order_date' => OrderSortField::OrderDate, 'source_quotation' => OrderSortField::SourceQuotation, 'status' => OrderSortField::Status];
    }
}
