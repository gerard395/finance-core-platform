<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Application\Identity\PermissionAuthorizer;
use App\Application\Sales\CancelOrder;
use App\Application\Sales\ConfirmOrder;
use App\Application\Sales\OrderWriteResult;
use App\Domain\Identity\Definitions\SalesPermission;
use App\Domain\Sales\ValueObjects\OrderId;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class OrderLifecycleController extends Controller
{
    public function __construct(private readonly ConfirmOrder $confirm, private readonly CancelOrder $cancel, private readonly PermissionAuthorizer $permissions) {}

    public function confirm(Request $request, string $order): RedirectResponse
    {
        return $this->execute($request, $order, fn ($admin, $id) => $this->confirm->execute($admin, $id), 'Order bevestigd.');
    }

    public function cancel(Request $request, string $order): RedirectResponse
    {
        return $this->execute($request, $order, fn ($admin, $id) => $this->cancel->execute($admin, $id), 'Order geannuleerd.');
    }

    private function execute(Request $request, string $order, callable $action, string $message): RedirectResponse
    {
        /** @var ActiveAdministrationContext $context */
        $context = $request->attributes->get('administration_context');
        try {
            $id = new OrderId(new Uuid($order));
        } catch (InvalidArgumentException) {
            abort(404);
        }
        $result = $action($context->administration->id(), $id);
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
