<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Application\Identity\PermissionAuthorizer;
use App\Application\Sales\CreateOrderFromQuotation;
use App\Application\Sales\CreateOrderFromQuotationStatus;
use App\Domain\Identity\Definitions\SalesPermission;
use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\CreateOrderFromQuotationRequest;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

final class QuotationOrderController extends Controller
{
    public function __construct(
        private readonly CreateOrderFromQuotation $createOrder,
        private readonly PermissionAuthorizer $permissions,
    ) {}

    public function __invoke(CreateOrderFromQuotationRequest $request, string $quotation): RedirectResponse
    {
        /** @var ActiveAdministrationContext $context */
        $context = $request->attributes->get('administration_context');
        $quotationId = $this->quotationId($quotation);
        $validated = $request->validated();
        $result = $this->createOrder->execute(
            $context->administration->id(),
            $quotationId,
            new DateTimeImmutable($validated['order_date']),
        );

        if ($result->status() === CreateOrderFromQuotationStatus::NotFound) {
            abort(404);
        }

        [$flashKey, $message] = match ($result->status()) {
            CreateOrderFromQuotationStatus::Success => ['status', 'Order aangemaakt.'],
            CreateOrderFromQuotationStatus::AlreadyConverted => ['status', 'Voor deze offerte bestaat al een order.'],
            CreateOrderFromQuotationStatus::InvalidSourceState => ['error', 'Deze offerte kan niet naar een order worden omgezet.'],
            CreateOrderFromQuotationStatus::SequenceMissing, CreateOrderFromQuotationStatus::SequenceInactive => ['error', 'De ordernummerreeks is niet beschikbaar.'],
            CreateOrderFromQuotationStatus::DuplicateIdentity, CreateOrderFromQuotationStatus::PersistenceConflict => ['error', 'De order kon niet worden aangemaakt. Probeer het later opnieuw.'],
            CreateOrderFromQuotationStatus::NotFound => throw new InvalidArgumentException('NotFound is handled before result mapping.'),
        };

        if ($result->orderId() !== null && $this->permissions->allows($context->permissionIds, SalesPermission::View->id())) {
            return redirect()->route('sales.orders.show', $result->orderId()->toString())->with($flashKey, $message);
        }

        return redirect()->route('app')->with($flashKey, $message);
    }

    private function quotationId(string $value): QuotationId
    {
        try {
            return new QuotationId(new Uuid($value));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }
}
