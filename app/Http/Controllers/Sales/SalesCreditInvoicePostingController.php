<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Application\Identity\PermissionAuthorizer;
use App\Application\Sales\PostSalesCreditInvoice;
use App\Application\Sales\PostSalesCreditInvoiceStatus;
use App\Domain\Identity\Definitions\SalesPermission;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

final class SalesCreditInvoicePostingController extends Controller
{
    public function __construct(
        private readonly PostSalesCreditInvoice $postCreditInvoice,
        private readonly PermissionAuthorizer $permissions,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(Request $request, string $creditInvoice): RedirectResponse
    {
        /** @var ActiveAdministrationContext $context */
        $context = $request->attributes->get('administration_context');
        $creditInvoiceId = $this->creditInvoiceId($creditInvoice);
        $result = $this->postCreditInvoice->execute($context->administration->id(), $creditInvoiceId);

        if ($result->status() === PostSalesCreditInvoiceStatus::NotFound) {
            abort(404);
        }
        if ($result->status() === PostSalesCreditInvoiceStatus::FinancialStateInconsistent) {
            $this->logger->error('Sales credit invoice financial state is inconsistent.', [
                'administration_id' => $context->administration->id()->toString(),
                'sales_credit_invoice_id' => $creditInvoiceId->toString(),
            ]);
        }

        [$flashKey, $message] = match ($result->status()) {
            PostSalesCreditInvoiceStatus::Success => ['status', 'Creditfactuur is geboekt.'],
            PostSalesCreditInvoiceStatus::AlreadyPosted => ['status', 'Deze creditfactuur is al geboekt.'],
            PostSalesCreditInvoiceStatus::ConfigurationMissing => ['error', 'De verkoopboekingsconfiguratie is nog niet volledig ingesteld.'],
            PostSalesCreditInvoiceStatus::ConfigurationInvalid => ['error', 'De verkoopboekingsconfiguratie is ongeldig of niet meer beschikbaar.'],
            PostSalesCreditInvoiceStatus::SourceFinancialStateInvalid => ['error', 'De oorspronkelijke factuur kan financieel niet veilig worden gecrediteerd.'],
            PostSalesCreditInvoiceStatus::FinancialStateInconsistent => ['error', 'De financiële status van deze creditfactuur is niet consistent. Controle is vereist.'],
            PostSalesCreditInvoiceStatus::InvalidState => ['error', 'Deze creditfactuur kan in de huidige status niet worden geboekt.'],
            PostSalesCreditInvoiceStatus::PostingFailure => ['error', 'De creditfactuur kon niet worden geboekt. Probeer het later opnieuw.'],
            PostSalesCreditInvoiceStatus::NotFound => throw new InvalidArgumentException('NotFound is handled before result mapping.'),
        };

        return $this->permissions->allows($context->permissionIds, SalesPermission::View->id())
            ? redirect()->route('sales.credit-invoices.show', $creditInvoiceId->toString())->with($flashKey, $message)
            : redirect()->route('app')->with($flashKey, $message);
    }

    private function creditInvoiceId(string $value): SalesCreditInvoiceId
    {
        try {
            return new SalesCreditInvoiceId(new Uuid($value));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }
}
