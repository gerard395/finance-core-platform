<?php

declare(strict_types=1);

namespace App\Http\Controllers\Purchasing;

use App\Application\Identity\PermissionAuthorizer;
use App\Application\Purchasing\CancelPurchaseCreditInvoice;
use App\Application\Purchasing\FinalizePurchaseCreditInvoice;
use App\Application\Purchasing\PurchaseCreditMutationResult;
use App\Domain\Identity\Definitions\PurchasingPermission;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceId;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class PurchaseCreditLifecycleController extends Controller
{
    public function __construct(private FinalizePurchaseCreditInvoice $finalizeCredit, private CancelPurchaseCreditInvoice $cancelCredit, private PermissionAuthorizer $permissions) {}

    public function finalize(Request $request, string $credit): RedirectResponse
    {
        $context = $request->attributes->get('administration_context');
        $id = $this->id($credit);
        $result = $this->finalizeCredit->execute($context->administration->id(), $id, $context->user->id());
        if ($result === PurchaseCreditMutationResult::NotFound) {
            abort(404);
        }

        return $this->redirect($context, $id)->with($result === PurchaseCreditMutationResult::Success || $result === PurchaseCreditMutationResult::AlreadyFinalized ? 'status' : 'error', $result === PurchaseCreditMutationResult::Success ? 'Creditnota is gefinaliseerd.' : ($result === PurchaseCreditMutationResult::AlreadyFinalized ? 'Creditnota was al gefinaliseerd.' : 'De creditnota is niet compleet of kan niet worden gefinaliseerd.'));
    }

    public function cancel(Request $request, string $credit): RedirectResponse
    {
        $context = $request->attributes->get('administration_context');
        $id = $this->id($credit);
        $result = $this->cancelCredit->execute($context->administration->id(), $id, $context->user->id());
        if ($result === PurchaseCreditMutationResult::NotFound) {
            abort(404);
        }

        return $this->redirect($context, $id)->with($result === PurchaseCreditMutationResult::Success || $result === PurchaseCreditMutationResult::AlreadyCancelled ? 'status' : 'error', $result === PurchaseCreditMutationResult::InvalidState ? 'Een geboekte creditnota kan niet worden geannuleerd.' : 'Creditnota is geannuleerd.');
    }

    private function id(string $value): PurchaseCreditInvoiceId
    {
        try {
            return new PurchaseCreditInvoiceId(new Uuid($value));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    private function redirect($context, PurchaseCreditInvoiceId $id): RedirectResponse
    {
        return $this->permissions->allows($context->permissionIds, PurchasingPermission::View->id()) ? redirect()->route('purchasing.credits.show', $id->toString()) : redirect()->route('app');
    }
}
