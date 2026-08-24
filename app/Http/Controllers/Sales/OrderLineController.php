<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Application\Identity\PermissionAuthorizer;
use App\Application\Sales\AddOrderLine;
use App\Application\Sales\OrderDetailReadRepository;
use App\Application\Sales\OrderWriteResult;
use App\Application\Sales\RemoveOrderLine;
use App\Application\Sales\UpdateOrderLine;
use App\Domain\Identity\Definitions\SalesPermission;
use App\Domain\Sales\Entities\OrderLine;
use App\Domain\Sales\ValueObjects\OrderId;
use App\Domain\Sales\ValueObjects\OrderLineId;
use App\Domain\Shared\Commerce\ValueObjects\LineDescription;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\OrderLineRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class OrderLineController extends Controller
{
    public function __construct(
        private readonly OrderDetailReadRepository $details,
        private readonly AddOrderLine $addLine,
        private readonly UpdateOrderLine $updateLine,
        private readonly RemoveOrderLine $removeLine,
        private readonly PermissionAuthorizer $permissions,
    ) {}

    public function store(OrderLineRequest $request, string $order): RedirectResponse
    {
        [$context, $id, $detail] = $this->document($request, $order);
        $line = $this->line(new OrderLineId(new Uuid(Str::uuid()->toString())), $request->validated(), $detail->currency());

        return $this->redirect($context, $id, $this->addLine->execute($context->administration->id(), $id, $line), 'Regel toegevoegd.');
    }

    public function update(OrderLineRequest $request, string $order, string $line): RedirectResponse
    {
        [$context, $id, $detail] = $this->document($request, $order);
        $lineId = $this->lineId($line);
        abort_unless($this->contains($detail->lines(), $lineId), 404);
        $replacement = $this->line($lineId, $request->validated(), $detail->currency());

        return $this->redirect($context, $id, $this->updateLine->execute($context->administration->id(), $id, $replacement), 'Regel bijgewerkt.');
    }

    public function destroy(Request $request, string $order, string $line): RedirectResponse
    {
        [$context, $id, $detail] = $this->document($request, $order);
        $lineId = $this->lineId($line);
        abort_unless($this->contains($detail->lines(), $lineId), 404);

        return $this->redirect($context, $id, $this->removeLine->execute($context->administration->id(), $id, $lineId), 'Regel verwijderd.');
    }

    private function line(OrderLineId $id, array $input, $currency): OrderLine
    {
        return new OrderLine($id, new LineDescription($input['description']), new Quantity($input['quantity']), new Money($input['unit_price'], $currency));
    }

    private function document(Request $request, string $order): array
    {
        /** @var ActiveAdministrationContext $context */
        $context = $request->attributes->get('administration_context');
        $id = $this->orderId($order);
        $detail = $this->details->find($context->administration->id(), $id);
        abort_if($detail === null, 404);

        return [$context, $id, $detail];
    }

    private function orderId(string $value): OrderId
    {
        try {
            return new OrderId(new Uuid($value));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    private function lineId(string $value): OrderLineId
    {
        try {
            return new OrderLineId(new Uuid($value));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    private function contains(array $lines, OrderLineId $id): bool
    {
        foreach ($lines as $line) {
            if ($line->id()->equals($id)) {
                return true;
            }
        }

        return false;
    }

    private function redirect(ActiveAdministrationContext $context, OrderId $id, OrderWriteResult $result, string $message): RedirectResponse
    {
        if ($result === OrderWriteResult::NotFound) {
            abort(404);
        }
        if ($result !== OrderWriteResult::Success) {
            return back()->with('error', 'De actie is niet toegestaan in de huidige orderstatus.');
        }

        return $this->permissions->allows($context->permissionIds, SalesPermission::View->id())
            ? redirect()->route('sales.orders.show', $id->toString())->with('status', $message)
            : redirect()->route('app')->with('status', $message);
    }
}
