<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Application\Sales\DeliveryRecipientOverride;
use App\Application\Sales\PrepareSalesDocumentArtifact;
use App\Application\Sales\QueueSalesDocumentDelivery;
use App\Application\Sales\ReadDocumentArtifact;
use App\Application\Sales\SalesDocumentSource;
use App\Domain\Relations\ValueObjects\ContactName;
use App\Domain\Relations\ValueObjects\EmailAddress;
use App\Domain\Sales\ValueObjects\DeliveryRequestId;
use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Controllers\Controller;
use App\Presentation\Sales\SalesDocumentDeliveryPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

final class SalesDocumentDeliveryController extends Controller
{
    public function __construct(private readonly QueueSalesDocumentDelivery $queue, private readonly PrepareSalesDocumentArtifact $prepare, private readonly ReadDocumentArtifact $read) {}

    public function quotation(Request $request, string $quotation): RedirectResponse
    {
        return $this->deliver($request, $this->quotationSource($quotation), false);
    }

    public function resendQuotation(Request $request, string $quotation): RedirectResponse
    {
        return $this->deliver($request, $this->quotationSource($quotation), true);
    }

    public function invoice(Request $request, string $invoice): RedirectResponse
    {
        return $this->deliver($request, $this->invoiceSource($invoice), false);
    }

    public function resendInvoice(Request $request, string $invoice): RedirectResponse
    {
        return $this->deliver($request, $this->invoiceSource($invoice), true);
    }

    public function credit(Request $request, string $creditInvoice): RedirectResponse
    {
        return $this->deliver($request, $this->creditSource($creditInvoice), false);
    }

    public function resendCredit(Request $request, string $creditInvoice): RedirectResponse
    {
        return $this->deliver($request, $this->creditSource($creditInvoice), true);
    }

    public function downloadQuotation(Request $request, string $quotation): Response
    {
        return $this->download($request, $this->quotationSource($quotation));
    }

    public function downloadInvoice(Request $request, string $invoice): Response
    {
        return $this->download($request, $this->invoiceSource($invoice));
    }

    public function downloadCredit(Request $request, string $creditInvoice): Response
    {
        return $this->download($request, $this->creditSource($creditInvoice));
    }

    private function deliver(Request $request, SalesDocumentSource $source, bool $resend): RedirectResponse
    {
        $validated = $request->validate([
            'delivery_request_id' => ['required', 'uuid'], 'use_recipient_override' => ['nullable', 'boolean'],
            'recipient_email' => ['exclude_unless:use_recipient_override,1', 'required', 'email:rfc', 'max:255'],
            'recipient_name' => ['exclude_unless:use_recipient_override,1', 'nullable', 'string', 'min:2', 'max:255'],
        ]);
        $context = $this->context($request);
        $override = ($validated['use_recipient_override'] ?? false)
            ? new DeliveryRecipientOverride(new EmailAddress($validated['recipient_email']), isset($validated['recipient_name']) ? new ContactName($validated['recipient_name']) : null)
            : null;
        $result = $this->queue->execute($context->administration->id(), new DeliveryRequestId(new Uuid($validated['delivery_request_id'])), $source, $context->user->id(), $resend, $override);
        if (! $result->queued()) {
            return back()->with('error', SalesDocumentDeliveryPresenter::readiness($result->status));
        }

        return back()->with('status', 'Document is klaargezet voor verzending.');
    }

    private function download(Request $request, SalesDocumentSource $source): Response
    {
        $context = $this->context($request);
        $prepared = $this->prepare->execute($context->administration->id(), $source);
        abort_if($prepared->artifact === null, 404);
        $read = $this->read->execute($context->administration->id(), $prepared->artifact->id);
        abort_if(! $read->integrityValid || $read->bytes === null || $read->artifact === null, 404);

        return response($read->bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$read->artifact->filename.'"', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function quotationSource(string $id): SalesDocumentSource
    {
        try {
            return SalesDocumentSource::quotation(new QuotationId(new Uuid($id)));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    private function invoiceSource(string $id): SalesDocumentSource
    {
        try {
            return SalesDocumentSource::invoice(new SalesInvoiceId(new Uuid($id)));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    private function creditSource(string $id): SalesDocumentSource
    {
        try {
            return SalesDocumentSource::creditInvoice(new SalesCreditInvoiceId(new Uuid($id)));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    private function context(Request $request): ActiveAdministrationContext
    {
        return $request->attributes->get('administration_context');
    }
}
