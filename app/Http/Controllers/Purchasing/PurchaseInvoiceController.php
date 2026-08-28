<?php

declare(strict_types=1);

namespace App\Http\Controllers\Purchasing;

use App\Application\Accounting\OpenItemReadRepository;
use App\Application\Identity\PermissionAuthorizer;
use App\Application\Purchasing\CreatePurchaseInvoice;
use App\Application\Purchasing\CreatePurchaseInvoiceStatus;
use App\Application\Purchasing\GetPurchaseInvoice;
use App\Application\Purchasing\GetPurchaseInvoiceMasterData;
use App\Application\Purchasing\GetPurchaseInvoicePosting;
use App\Application\Purchasing\ListPurchaseInvoices;
use App\Application\Purchasing\PurchaseInvoiceDraftInput;
use App\Application\Purchasing\PurchaseInvoiceLineInput;
use App\Application\Purchasing\UpdateDraftPurchaseInvoice;
use App\Application\Purchasing\UpdateDraftPurchaseInvoiceResult;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Identity\Definitions\AdministrationPermission;
use App\Domain\Identity\Definitions\PurchasingPermission;
use App\Domain\Purchasing\Enums\PurchaseInvoiceStatus;
use App\Domain\Purchasing\ValueObjects\PurchaseDocumentAddress;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;
use App\Domain\Purchasing\ValueObjects\SupplierInvoiceNumber;
use App\Domain\Relations\ValueObjects\AddressLine;
use App\Domain\Relations\ValueObjects\City;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\PostalCode;
use App\Domain\Relations\ValueObjects\SupplierId;
use App\Domain\Shared\Commerce\ValueObjects\LineDescription;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\PurchaseInvoiceRequest;
use App\Presentation\Formatting\DutchMoneyFormatter;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

final class PurchaseInvoiceController extends Controller
{
    public function __construct(private ListPurchaseInvoices $list, private GetPurchaseInvoice $get, private GetPurchaseInvoiceMasterData $masterData, private CreatePurchaseInvoice $createInvoice, private UpdateDraftPurchaseInvoice $updateInvoice, private GetPurchaseInvoicePosting $getPosting, private OpenItemReadRepository $openItems, private PermissionAuthorizer $permissions, private DutchMoneyFormatter $money) {}

    public function index(Request $request): View
    {
        $context = $this->context($request);

        return view('purchasing.invoices.index', $this->viewData($context) + ['invoices' => $this->list->execute($context->administration->id())]);
    }

    public function show(Request $request, string $invoice): View
    {
        $context = $this->context($request);
        $id = $this->id($invoice);
        $detail = $this->get->execute($context->administration->id(), $id);
        abort_if($detail === null, 404);
        $posting = $this->getPosting->execute($context->administration->id(), $id);
        $openItem = null;
        if ($posting !== null) {
            $asOf = new PostingDate(max(new DateTimeImmutable('today'), $posting->postingDate->value()));
            foreach ($this->openItems->findForAdministrationAsOf($context->administration->id(), $asOf) as $candidate) {
                if ($candidate->id()->equals($posting->openItemId)) {
                    $openItem = $candidate;
                    break;
                }
            }
        }

        return view('purchasing.invoices.show', $this->viewData($context) + ['invoice' => $detail, 'posting' => $posting, 'openItem' => $openItem]);
    }

    public function create(Request $request): View
    {
        $context = $this->context($request);

        return view('purchasing.invoices.create', $this->viewData($context) + $this->masterData->execute($context->administration->id()));
    }

    public function store(PurchaseInvoiceRequest $request): RedirectResponse
    {
        $context = $this->context($request);
        try {
            $input = $this->input($request->validated());
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['invoice' => 'De invoer bevat een ongeldige waarde.']);
        }
        $result = $this->createInvoice->execute($context->administration->id(), $input);
        if ($result->status !== CreatePurchaseInvoiceStatus::Success || $result->id === null) {
            $message = match ($result->status) {
                CreatePurchaseInvoiceStatus::DuplicateSupplierInvoice => 'Dit leveranciersfactuurnummer bestaat al voor deze leverancier.',
                CreatePurchaseInvoiceStatus::SupplierNotFound, CreatePurchaseInvoiceStatus::InvalidSupplier => 'De geselecteerde leverancier is niet beschikbaar.',
                default => 'Een rekening of btw-code is niet beschikbaar voor deze inkoopfactuur.',
            };

            return back()->withInput()->withErrors(['invoice' => $message]);
        }

        return $this->redirect($context, $result->id)->with('status', 'Inkoopfactuur aangemaakt.');
    }

    public function edit(Request $request, string $invoice): View
    {
        $context = $this->context($request);
        $detail = $this->get->execute($context->administration->id(), $this->id($invoice));
        abort_if($detail === null, 404);
        abort_if($detail->status() !== PurchaseInvoiceStatus::Draft, 409);

        return view('purchasing.invoices.edit', $this->viewData($context) + $this->masterData->execute($context->administration->id()) + ['invoice' => $detail]);
    }

    public function update(PurchaseInvoiceRequest $request, string $invoice): RedirectResponse
    {
        $context = $this->context($request);
        $id = $this->id($invoice);
        try {
            $input = $this->input($request->validated());
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['invoice' => 'De invoer bevat een ongeldige waarde.']);
        }
        $result = $this->updateInvoice->execute($context->administration->id(), $id, $input);
        if ($result === UpdateDraftPurchaseInvoiceResult::NotFound) {
            abort(404);
        }
        if ($result !== UpdateDraftPurchaseInvoiceResult::Saved) {
            $message = $result === UpdateDraftPurchaseInvoiceResult::DuplicateSupplierInvoice ? 'Dit leveranciersfactuurnummer bestaat al voor deze leverancier.' : 'De Draft kon niet worden bijgewerkt; controleer status en masterdata.';

            return back()->withInput()->withErrors(['invoice' => $message]);
        }

        return $this->redirect($context, $id)->with('status', 'Inkoopfactuur bijgewerkt.');
    }

    private function input(array $data): PurchaseInvoiceDraftInput
    {
        $currency = new Currency('EUR');
        $lines = [];
        foreach ($data['lines'] as $line) {
            if ($line['_delete'] ?? false) {
                continue;
            }
            $values = array_map(static fn (string $key): string => trim((string) ($line[$key] ?? '')), ['description', 'quantity', 'unit_price', 'ledger_account_id', 'tax_code_id']);
            if (count(array_filter($values, static fn (string $value): bool => $value !== '')) === 0) {
                continue;
            }
            if (count(array_filter($values, static fn (string $value): bool => $value !== '')) !== count($values)) {
                throw new InvalidArgumentException('Incomplete purchase invoice line.');
            }
            $lines[] = new PurchaseInvoiceLineInput(new LineDescription($line['description']), new Quantity($line['quantity']), new Money($line['unit_price'], $currency), new LedgerAccountId(new Uuid($line['ledger_account_id'])), new TaxCodeId(new Uuid($line['tax_code_id'])), (bool) ($line['fully_deductible'] ?? false));
        }

        return new PurchaseInvoiceDraftInput(new SupplierId(new Uuid($data['supplier_id'])), new SupplierInvoiceNumber($data['supplier_invoice_number']), new DateTimeImmutable($data['invoice_date']), new DateTimeImmutable($data['received_date']), empty($data['supply_date']) ? null : new DateTimeImmutable($data['supply_date']), new DateTimeImmutable($data['due_date']), $currency, new PurchaseDocumentAddress(new AddressLine($data['address_line_1']), empty($data['address_line_2']) ? null : new AddressLine($data['address_line_2']), new PostalCode($data['postal_code']), new City($data['city']), new CountryCode(strtoupper($data['country_code']))), $lines);
    }

    private function viewData(ActiveAdministrationContext $context): array
    {
        return ['domainUser' => $context->user, 'administrationContext' => $context, 'canManageDrafts' => $this->can($context, PurchasingPermission::ManageInvoiceDrafts), 'canFinalize' => $this->can($context, PurchasingPermission::FinalizeInvoices), 'canPost' => $this->can($context, PurchasingPermission::PostInvoices), 'canManageCreditDrafts' => $this->can($context, PurchasingPermission::ManageCreditDrafts), 'canUpdateSettings' => $this->permissions->allows($context->permissionIds, AdministrationPermission::UpdateSettings->id()), 'moneyFormatter' => $this->money];
    }

    private function context(Request $request): ActiveAdministrationContext
    {
        return $request->attributes->get('administration_context');
    }

    private function can(ActiveAdministrationContext $context, PurchasingPermission $permission): bool
    {
        return $this->permissions->allows($context->permissionIds, $permission->id());
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
        return $this->can($context, PurchasingPermission::View) ? redirect()->route('purchasing.invoices.show', $id->toString()) : redirect()->route('app');
    }
}
