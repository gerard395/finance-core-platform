<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Application\Identity\PermissionAuthorizer;
use App\Application\Sales\CancelSalesCreditInvoice;
use App\Application\Sales\FinalizeSalesCreditInvoice;
use App\Application\Sales\SalesCreditInvoiceDetailReadRepository;
use App\Application\Sales\SalesCreditInvoiceWriteResult;
use App\Domain\Identity\Definitions\SalesPermission;
use App\Domain\Sales\Enums\SalesCreditInvoiceStatus;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class SalesCreditInvoiceLifecycleController extends Controller
{
    public function __construct(private readonly SalesCreditInvoiceDetailReadRepository $details, private readonly FinalizeSalesCreditInvoice $finalizeCredit, private readonly CancelSalesCreditInvoice $cancelCredit, private readonly PermissionAuthorizer $permissions) {}

    public function finalize(Request $request, string $creditInvoice): RedirectResponse
    {
        $context = $this->context($request);
        $id = $this->id($creditInvoice);

        return $this->result($context, $id, $this->finalizeCredit->execute($context->administration->id(), $id), 'Creditfactuur definitief gemaakt.');
    }

    public function cancel(Request $request, string $creditInvoice): RedirectResponse
    {
        $context = $this->context($request);
        $id = $this->id($creditInvoice);
        $detail = $this->details->find($context->administration->id(), $id);
        abort_if($detail === null, 404);
        $permission = $detail->invoice->status() === SalesCreditInvoiceStatus::Draft ? SalesPermission::ManageCreditInvoiceDrafts : SalesPermission::IssueCreditInvoices;
        abort_unless($this->permissions->allows($context->permissionIds, $permission->id()), 403);

        return $this->result($context, $id, $this->cancelCredit->execute($context->administration->id(), $id), 'Creditfactuur geannuleerd.');
    }

    private function result(ActiveAdministrationContext $context, SalesCreditInvoiceId $id, SalesCreditInvoiceWriteResult $result, string $message): RedirectResponse
    {
        if ($result === SalesCreditInvoiceWriteResult::NotFound) {
            abort(404);
        }
        if ($result !== SalesCreditInvoiceWriteResult::Success) {
            return back()->with('error', 'De actie is niet toegestaan voor deze creditfactuur.');
        }

        return $this->permissions->allows($context->permissionIds, SalesPermission::View->id()) ? redirect()->route('sales.credit-invoices.show', $id->toString())->with('status', $message) : redirect()->route('app')->with('status', $message);
    }

    private function context(Request $request): ActiveAdministrationContext
    {
        return $request->attributes->get('administration_context');
    }

    private function id(string $value): SalesCreditInvoiceId
    {
        try {
            return new SalesCreditInvoiceId(new Uuid($value));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }
}
