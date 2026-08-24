<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Application\Identity\PermissionAuthorizer;
use App\Application\Sales\CancelSalesInvoice;
use App\Application\Sales\FinalizeSalesInvoice;
use App\Application\Sales\SalesInvoiceDetailReadRepository;
use App\Application\Sales\SalesInvoiceWriteResult;
use App\Domain\Identity\Definitions\SalesPermission;
use App\Domain\Sales\Enums\SalesInvoiceStatus;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class SalesInvoiceLifecycleController extends Controller
{
    public function __construct(private readonly SalesInvoiceDetailReadRepository $details, private readonly FinalizeSalesInvoice $finalize, private readonly CancelSalesInvoice $cancel, private readonly PermissionAuthorizer $permissions) {}

    public function finalize(Request $request, string $invoice): RedirectResponse
    {
        return $this->execute($request, $invoice, fn ($admin, $id) => $this->finalize->execute($admin, $id), 'Verkoopfactuur definitief gemaakt.');
    }

    public function cancel(Request $request, string $invoice): RedirectResponse
    {
        /** @var ActiveAdministrationContext $context */
        $context = $request->attributes->get('administration_context');
        $id = $this->invoiceId($invoice);
        $detail = $this->details->find($context->administration->id(), $id);
        abort_if($detail === null, 404);
        $permission = $detail->status() === SalesInvoiceStatus::Draft ? SalesPermission::ManageInvoiceDrafts : SalesPermission::IssueInvoices;
        abort_unless($this->permissions->allows($context->permissionIds, $permission->id()), 403);

        return $this->result($context, $id, $this->cancel->execute($context->administration->id(), $id), 'Verkoopfactuur geannuleerd.');
    }

    private function execute(Request $request, string $invoice, callable $action, string $message): RedirectResponse
    {
        /** @var ActiveAdministrationContext $context */
        $context = $request->attributes->get('administration_context');
        $id = $this->invoiceId($invoice);

        return $this->result($context, $id, $action($context->administration->id(), $id), $message);
    }

    private function result(ActiveAdministrationContext $context, SalesInvoiceId $id, SalesInvoiceWriteResult $result, string $message): RedirectResponse
    {
        if ($result === SalesInvoiceWriteResult::NotFound) {
            abort(404);
        }
        if ($result !== SalesInvoiceWriteResult::Success) {
            $error = $result === SalesInvoiceWriteResult::TaxCalculationFailure
                ? 'De factuur kan niet exact zonder afronding worden berekend.'
                : 'De actie is niet toegestaan of de factuur is nog niet compleet.';

            return back()->with('error', $error);
        }

        return $this->permissions->allows($context->permissionIds, SalesPermission::View->id())
            ? redirect()->route('sales.invoices.show', $id->toString())->with('status', $message)
            : redirect()->route('app')->with('status', $message);
    }

    private function invoiceId(string $value): SalesInvoiceId
    {
        try {
            return new SalesInvoiceId(new Uuid($value));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }
}
