<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Application\Identity\PermissionAuthorizer;
use App\Application\Sales\AcceptQuotation;
use App\Application\Sales\ExpireQuotation;
use App\Application\Sales\QuotationWriteResult;
use App\Application\Sales\RejectQuotation;
use App\Application\Sales\SendQuotation;
use App\Domain\Identity\Definitions\SalesPermission;
use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class QuotationLifecycleController extends Controller
{
    public function __construct(private readonly SendQuotation $send, private readonly AcceptQuotation $accept, private readonly RejectQuotation $reject, private readonly ExpireQuotation $expire, private readonly PermissionAuthorizer $permissions) {}

    public function send(Request $request, string $quotation): RedirectResponse
    {
        return $this->execute($request, $quotation, fn ($admin, $id) => $this->send->execute($admin, $id), 'Offerte verzonden.');
    }

    public function accept(Request $request, string $quotation): RedirectResponse
    {
        return $this->execute($request, $quotation, fn ($admin, $id) => $this->accept->execute($admin, $id), 'Offerte geaccepteerd.');
    }

    public function reject(Request $request, string $quotation): RedirectResponse
    {
        return $this->execute($request, $quotation, fn ($admin, $id) => $this->reject->execute($admin, $id), 'Offerte afgewezen.');
    }

    public function expire(Request $request, string $quotation): RedirectResponse
    {
        return $this->execute($request, $quotation, fn ($admin, $id) => $this->expire->execute($admin, $id), 'Offerte verlopen.');
    }

    private function execute(Request $request, string $quotation, callable $action, string $message): RedirectResponse
    {
        /** @var ActiveAdministrationContext $context */
        $context = $request->attributes->get('administration_context');
        try {
            $id = new QuotationId(new Uuid($quotation));
        } catch (InvalidArgumentException) {
            abort(404);
        }
        $result = $action($context->administration->id(), $id);
        if ($result === QuotationWriteResult::NotFound) {
            abort(404);
        }
        if ($result !== QuotationWriteResult::Success) {
            return back()->with('error', 'De actie is niet toegestaan in de huidige offertestatus.');
        }

        return $this->permissions->allows($context->permissionIds, SalesPermission::View->id())
            ? redirect()->route('sales.quotations.show', $id->toString())->with('status', $message)
            : redirect()->route('app')->with('status', $message);
    }
}
