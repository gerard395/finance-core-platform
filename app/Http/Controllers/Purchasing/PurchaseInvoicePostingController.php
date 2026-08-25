<?php

declare(strict_types=1);

namespace App\Http\Controllers\Purchasing;

use App\Application\Identity\PermissionAuthorizer;
use App\Application\Purchasing\PostPurchaseInvoice;
use App\Application\Purchasing\PostPurchaseInvoiceStatus;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Identity\Definitions\PurchasingPermission;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Controllers\Controller;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class PurchaseInvoicePostingController extends Controller
{
    public function __construct(private PostPurchaseInvoice $postInvoice, private PermissionAuthorizer $permissions) {}

    public function __invoke(Request $request, string $invoice): RedirectResponse
    {
        $validated = $request->validate(['posting_date' => ['required', 'date_format:Y-m-d']]);
        $context = $request->attributes->get('administration_context');
        $id = $this->id($invoice);
        $result = $this->postInvoice->execute($context->administration->id(), $id, new PostingDate(new DateTimeImmutable($validated['posting_date'])));
        if ($result->status === PostPurchaseInvoiceStatus::NotFound) {
            abort(404);
        }
        [$key, $message] = match ($result->status) {
            PostPurchaseInvoiceStatus::Success => ['status', 'Inkoopfactuur is geboekt.'],
            PostPurchaseInvoiceStatus::AlreadyPosted => ['status', 'Deze inkoopfactuur is al geboekt.'],
            PostPurchaseInvoiceStatus::ConfigurationMissing => ['error', 'De inkoopboekingsinstellingen zijn nog niet ingericht.'],
            PostPurchaseInvoiceStatus::ConfigurationInvalid => ['error', 'De inkoopboekingsinstellingen verwijzen naar een inactief of ongeldig dagboek/rekening.'],
            PostPurchaseInvoiceStatus::InvalidState => ['error', 'Deze inkoopfactuur kan in de huidige status niet worden geboekt.'],
            PostPurchaseInvoiceStatus::FiscalStateInvalid => ['error', 'De fiscale gegevens van deze inkoopfactuur worden niet ondersteund.'],
            default => ['error', 'De inkoopfactuur kon niet worden geboekt. Probeer het later opnieuw.'],
        };

        return ($this->permissions->allows($context->permissionIds, PurchasingPermission::View->id()) ? redirect()->route('purchasing.invoices.show', $id->toString()) : redirect()->route('app'))->with($key, $message);
    }

    private function id(string $value): PurchaseInvoiceId
    {
        try {
            return new PurchaseInvoiceId(new Uuid($value));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }
}
