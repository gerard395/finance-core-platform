<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Application\Identity\PermissionAuthorizer;
use App\Application\Sales\PostSalesInvoice;
use App\Application\Sales\PostSalesInvoiceStatus;
use App\Domain\Identity\Definitions\SalesPermission;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

final class SalesInvoicePostingController extends Controller
{
    public function __construct(
        private readonly PostSalesInvoice $postInvoice,
        private readonly PermissionAuthorizer $permissions,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(Request $request, string $invoice): RedirectResponse
    {
        /** @var ActiveAdministrationContext $context */
        $context = $request->attributes->get('administration_context');
        $invoiceId = $this->invoiceId($invoice);
        $result = $this->postInvoice->execute($context->administration->id(), $invoiceId);

        if ($result->status() === PostSalesInvoiceStatus::NotFound) {
            abort(404);
        }

        if ($result->status() === PostSalesInvoiceStatus::FinancialStateInconsistent) {
            $this->logger->error('Sales invoice financial state is inconsistent.', [
                'administration_id' => $context->administration->id()->toString(),
                'sales_invoice_id' => $invoiceId->toString(),
            ]);
        }

        [$flashKey, $message] = match ($result->status()) {
            PostSalesInvoiceStatus::Success => ['status', 'Factuur is geboekt.'],
            PostSalesInvoiceStatus::AlreadyPosted => ['status', 'Deze factuur is al geboekt.'],
            PostSalesInvoiceStatus::ConfigurationMissing => ['error', 'De verkoopboekingsconfiguratie is nog niet volledig ingesteld.'],
            PostSalesInvoiceStatus::ConfigurationInvalid => ['error', 'De verkoopboekingsconfiguratie is ongeldig of niet meer beschikbaar.'],
            PostSalesInvoiceStatus::InvalidState => ['error', 'Deze factuur kan in de huidige status niet worden geboekt.'],
            PostSalesInvoiceStatus::FinancialStateInconsistent => ['error', 'De financiële status van deze factuur is niet consistent. Controle is vereist.'],
            PostSalesInvoiceStatus::PostingFailure => ['error', 'De factuur kon niet worden geboekt. Probeer het later opnieuw.'],
            PostSalesInvoiceStatus::NotFound => throw new InvalidArgumentException('NotFound is handled before result mapping.'),
        };

        return $this->permissions->allows($context->permissionIds, SalesPermission::View->id())
            ? redirect()->route('sales.invoices.show', $invoiceId->toString())->with($flashKey, $message)
            : redirect()->route('app')->with($flashKey, $message);
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
