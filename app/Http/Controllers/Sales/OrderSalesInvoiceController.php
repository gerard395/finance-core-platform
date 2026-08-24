<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Application\Fiscal\TaxCodeReadRepository;
use App\Application\Identity\PermissionAuthorizer;
use App\Application\Relations\AddressReadRepository;
use App\Application\Sales\CreateSalesInvoiceFromOrder;
use App\Application\Sales\CreateSalesInvoiceFromOrderLineInput;
use App\Application\Sales\CreateSalesInvoiceFromOrderStatus;
use App\Application\Sales\OrderDetailReadRepository;
use App\Application\Sales\OrderInvoicingProgressReader;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Identity\Definitions\RelationsPermission;
use App\Domain\Identity\Definitions\SalesPermission;
use App\Domain\Relations\Enums\AddressType;
use App\Domain\Relations\ValueObjects\AddressId;
use App\Domain\Sales\Enums\OrderStatus;
use App\Domain\Sales\ValueObjects\OrderId;
use App\Domain\Sales\ValueObjects\OrderInvoiceDraftRequestId;
use App\Domain\Sales\ValueObjects\OrderLineId;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreOrderSalesInvoiceRequest;
use App\Presentation\Sales\OrderStatusPresenter;
use DateTimeImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

final class OrderSalesInvoiceController extends Controller
{
    public function __construct(
        private OrderDetailReadRepository $orders,
        private OrderInvoicingProgressReader $progress,
        private AddressReadRepository $addresses,
        private TaxCodeReadRepository $taxCodes,
        private CreateSalesInvoiceFromOrder $createInvoice,
        private PermissionAuthorizer $permissions,
    ) {}

    public function create(Request $request, string $order): View
    {
        $context = $this->context($request);
        $orderId = $this->orderId($order);
        $data = $this->pageData($context, $orderId);
        abort_unless($this->isInvoiceable($data['order']->status()) && $data['hasAvailable'], 409);
        $requestId = new OrderInvoiceDraftRequestId(new Uuid(Str::uuid()->toString()));

        return view('sales.orders.invoice-create', $data + [
            'draftRequestToken' => Crypt::encryptString(json_encode(['order' => $orderId->toString(), 'request' => $requestId->toString()], JSON_THROW_ON_ERROR)),
        ]);
    }

    public function store(StoreOrderSalesInvoiceRequest $request, string $order): RedirectResponse
    {
        $context = $this->context($request);
        $orderId = $this->orderId($order);
        $validated = $request->validated();
        $requestId = $this->requestId($validated['draft_request_token'], $orderId);
        $lines = [];
        foreach ($validated['lines'] as $lineId => $input) {
            $quantity = $input['quantity'] ?? '';
            if ($quantity === '' || $quantity === '0') {
                continue;
            }
            if (empty($input['tax_code_id'])) {
                throw ValidationException::withMessages(["lines.$lineId.tax_code_id" => 'Selecteer een btw-code voor iedere gekozen regel.']);
            }
            try {
                $lines[] = new CreateSalesInvoiceFromOrderLineInput(
                    new OrderLineId(new Uuid((string) $lineId)),
                    new Quantity($quantity),
                    new TaxCodeId(new Uuid($input['tax_code_id'])),
                );
            } catch (InvalidArgumentException) {
                throw ValidationException::withMessages(['lines' => 'De gekozen factuurregels zijn ongeldig.']);
            }
        }
        $result = $this->createInvoice->execute(
            $context->administration->id(), $orderId, $requestId, new AddressId(new Uuid($validated['invoice_address_id'])),
            new DateTimeImmutable($validated['invoice_date']), new DateTimeImmutable($validated['due_date']), $lines,
        );
        if (in_array($result->status(), [CreateSalesInvoiceFromOrderStatus::Success, CreateSalesInvoiceFromOrderStatus::AlreadyCreated], true)) {
            $message = $result->status() === CreateSalesInvoiceFromOrderStatus::Success ? 'Verkoopfactuur aangemaakt.' : 'Deze factuur is al aangemaakt.';

            return $this->can($context, SalesPermission::View)
                ? redirect()->route('sales.invoices.show', $result->salesInvoiceId()?->toString())->with('status', $message)
                : redirect()->route('app')->with('status', $message);
        }

        return back()->withInput()->withErrors(['invoice' => $this->failureMessage($result->status())]);
    }

    private function pageData(ActiveAdministrationContext $context, OrderId $orderId): array
    {
        $order = $this->orders->find($context->administration->id(), $orderId);
        $progress = $this->progress->progress($context->administration->id(), $orderId);
        abort_if($order === null || $progress === null, 404);
        $byLine = [];
        foreach ($progress->lines() as $line) {
            $byLine[$line->orderLineId()->toString()] = $line;
        }
        $addresses = array_values(array_filter(
            $this->addresses->listForRelation($context->administration->id(), $order->customer()->relationId()),
            static fn ($address): bool => $address->active && $address->type === AddressType::Invoice,
        ));

        return [
            'domainUser' => $context->user,
            'administrationContext' => $context,
            'canViewRelations' => $this->permissions->allows($context->permissionIds, RelationsPermission::View->id()),
            'canViewSales' => $this->can($context, SalesPermission::View),
            'order' => $order,
            'progressByLine' => $byLine,
            'hasAvailable' => collect($progress->lines())->contains(static fn ($line): bool => ! $line->available()->isZero()),
            'invoiceAddresses' => $addresses,
            'taxCodes' => $this->taxCodes->findActiveForAdministrationAndDirection($context->administration->id(), TaxPostingDirection::Output),
            'statusPresenter' => OrderStatusPresenter::class,
        ];
    }

    private function requestId(string $token, OrderId $orderId): OrderInvoiceDraftRequestId
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($payload) || ($payload['order'] ?? null) !== $orderId->toString() || ! is_string($payload['request'] ?? null)) {
                throw new InvalidArgumentException;
            }

            return new OrderInvoiceDraftRequestId(new Uuid($payload['request']));
        } catch (DecryptException|InvalidArgumentException|\JsonException) {
            throw ValidationException::withMessages(['draft_request_token' => 'De factuuraanvraag is ongeldig. Vernieuw de pagina en probeer opnieuw.']);
        }
    }

    private function failureMessage(CreateSalesInvoiceFromOrderStatus $status): string
    {
        return match ($status) {
            CreateSalesInvoiceFromOrderStatus::InvalidOrderState => 'Deze order kan niet worden gefactureerd.',
            CreateSalesInvoiceFromOrderStatus::NothingToInvoice => 'Selecteer minimaal één hoeveelheid om te factureren.',
            CreateSalesInvoiceFromOrderStatus::QuantityExceedsRemaining => 'De gekozen hoeveelheid is niet meer volledig beschikbaar voor facturatie.',
            CreateSalesInvoiceFromOrderStatus::MissingInvoiceAddress => 'Er is geen geldig factuuradres beschikbaar.',
            CreateSalesInvoiceFromOrderStatus::TaxCodeNotFound, CreateSalesInvoiceFromOrderStatus::TaxCodeInactive, CreateSalesInvoiceFromOrderStatus::TaxCodeWrongDirection, CreateSalesInvoiceFromOrderStatus::TaxCalculationFailed => 'De gekozen btw-code kan niet veilig voor deze factuur worden gebruikt.',
            CreateSalesInvoiceFromOrderStatus::SequenceMissing, CreateSalesInvoiceFromOrderStatus::SequenceInactive => 'De factuurnummerreeks is niet beschikbaar.',
            CreateSalesInvoiceFromOrderStatus::NotFound => 'De order of een gekozen regel is niet beschikbaar.',
            default => 'De verkoopfactuur kon niet worden aangemaakt. Probeer het veilig opnieuw.',
        };
    }

    private function isInvoiceable(OrderStatus $status): bool
    {
        return in_array($status, [OrderStatus::Confirmed, OrderStatus::PartiallyInvoiced], true);
    }

    private function orderId(string $value): OrderId
    {
        try {
            return new OrderId(new Uuid($value));
        } catch (InvalidArgumentException) {
            abort(404);
        }
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
}
