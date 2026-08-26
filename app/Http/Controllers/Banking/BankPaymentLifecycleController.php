<?php

declare(strict_types=1);

namespace App\Http\Controllers\Banking;

use App\Application\Banking\BankTransactionResult;
use App\Application\Banking\CancelBankTransaction;
use App\Application\Banking\FinalizeBankTransaction;
use App\Application\Identity\PermissionAuthorizer;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Identity\Definitions\BankingPermission;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class BankPaymentLifecycleController extends Controller
{
    public function __construct(private FinalizeBankTransaction $finalizeTransaction, private CancelBankTransaction $cancelTransaction, private PermissionAuthorizer $permissions) {}

    public function finalize(Request $request, string $payment): RedirectResponse
    {
        $context = $this->context($request);
        $id = $this->id($payment);
        $result = $this->finalizeTransaction->execute($context->administration->id(), $id, $context->user->id());
        if ($result === BankTransactionResult::NotFound) {
            abort(404);
        }
        if (! in_array($result, [BankTransactionResult::Success, BankTransactionResult::AlreadyFinalized], true)) {
            return back()->with('error', match ($result) {
                BankTransactionResult::InvalidAllocation => 'De betaling is niet volledig toegewezen of een openstaande post heeft onvoldoende saldo.',
                BankTransactionResult::InvalidReference => 'De geselecteerde bankrekening is niet meer beschikbaar.',
                default => 'Deze bankbetaling kan in de huidige status niet worden gefinaliseerd.',
            });
        }

        return $this->redirect($context, $id)->with('status', $result === BankTransactionResult::Success ? 'Bankbetaling is gefinaliseerd.' : 'Deze bankbetaling was al gefinaliseerd.');
    }

    public function cancel(Request $request, string $payment): RedirectResponse
    {
        $context = $this->context($request);
        $id = $this->id($payment);
        $result = $this->cancelTransaction->execute($context->administration->id(), $id);
        if ($result === BankTransactionResult::NotFound) {
            abort(404);
        }
        if ($result !== BankTransactionResult::Success) {
            return back()->with('error', 'Deze bankbetaling kan niet worden geannuleerd.');
        }

        return $this->redirect($context, $id)->with('status', 'Bankbetaling is geannuleerd.');
    }

    private function context(Request $request): ActiveAdministrationContext
    {
        return $request->attributes->get('administration_context');
    }

    private function id(string $value): BankTransactionId
    {
        try {
            return new BankTransactionId(new Uuid($value));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    private function redirect(ActiveAdministrationContext $context, BankTransactionId $id): RedirectResponse
    {
        return $this->permissions->allows($context->permissionIds, BankingPermission::View->id()) ? redirect()->route('banking.payments.show', $id->toString()) : redirect()->route('app');
    }
}
