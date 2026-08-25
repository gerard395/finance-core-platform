<?php

declare(strict_types=1);

namespace App\Http\Controllers\Purchasing;

use App\Application\Identity\PermissionAuthorizer;
use App\Application\Purchasing\CancelPurchaseInvoice;
use App\Application\Purchasing\CancelPurchaseInvoiceResult;
use App\Application\Purchasing\FinalizePurchaseInvoice;
use App\Application\Purchasing\FinalizePurchaseInvoiceResult;
use App\Domain\Identity\Definitions\PurchasingPermission;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class PurchaseInvoiceLifecycleController extends Controller
{
    public function __construct(private FinalizePurchaseInvoice $finalizeInvoice, private CancelPurchaseInvoice $cancelInvoice, private PermissionAuthorizer $permissions) {}

    public function finalize(Request $request, string $invoice): RedirectResponse
    {
        $context = $this->context($request);
        $id = $this->id($invoice);
        $result = $this->finalizeInvoice->execute($context->administration->id(), $id, $context->user->id());
        if ($result === FinalizePurchaseInvoiceResult::NotFound) {
            abort(404);
        }
        if ($result !== FinalizePurchaseInvoiceResult::Success && $result !== FinalizePurchaseInvoiceResult::AlreadyFinalized) {
            return back()->with('error', 'De inkoopfactuur is niet compleet of kan niet worden gefinaliseerd.');
        }

        return $this->redirect($context, $id)->with('status', $result === FinalizePurchaseInvoiceResult::Success ? 'Inkoopfactuur is gefinaliseerd.' : 'Deze inkoopfactuur was al gefinaliseerd.');
    }

    public function cancel(Request $request, string $invoice): RedirectResponse
    {
        $context = $this->context($request);
        $id = $this->id($invoice);
        $result = $this->cancelInvoice->execute($context->administration->id(), $id);
        if ($result === CancelPurchaseInvoiceResult::NotFound) {
            abort(404);
        }
        if ($result !== CancelPurchaseInvoiceResult::Success && $result !== CancelPurchaseInvoiceResult::AlreadyCancelled) {
            return back()->with('error', 'Deze inkoopfactuur kan niet worden geannuleerd.');
        }

        return $this->redirect($context, $id)->with('status', 'Inkoopfactuur is geannuleerd.');
    }

    private function context(Request $request): ActiveAdministrationContext
    {
        return $request->attributes->get('administration_context');
    }

    private function id(string $value): PurchaseInvoiceId
    {
        try {
            return new PurchaseInvoiceId(new Uuid($value));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    private function redirect(ActiveAdministrationContext $context, PurchaseInvoiceId $id): RedirectResponse
    {
        return $this->permissions->allows($context->permissionIds, PurchasingPermission::View->id()) ? redirect()->route('purchasing.invoices.show', $id->toString()) : redirect()->route('app');
    }
}
