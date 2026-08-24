<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Application\Identity\PermissionAuthorizer;
use App\Application\Sales\AddSalesInvoiceLine;
use App\Application\Sales\RemoveSalesInvoiceLine;
use App\Application\Sales\SalesInvoiceDetail;
use App\Application\Sales\SalesInvoiceDetailReadRepository;
use App\Application\Sales\SalesInvoiceLineInput;
use App\Application\Sales\SalesInvoiceWriteResult;
use App\Application\Sales\UpdateSalesInvoiceLine;
use App\Domain\Fiscal\ValueObjects\TaxCodeId;
use App\Domain\Identity\Definitions\SalesPermission;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceLineId;
use App\Domain\Shared\Commerce\ValueObjects\LineDescription;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\SalesInvoiceLineRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class SalesInvoiceLineController extends Controller
{
    public function __construct(
        private readonly SalesInvoiceDetailReadRepository $details,
        private readonly AddSalesInvoiceLine $addLine,
        private readonly UpdateSalesInvoiceLine $updateLine,
        private readonly RemoveSalesInvoiceLine $removeLine,
        private readonly PermissionAuthorizer $permissions,
    ) {}

    public function store(SalesInvoiceLineRequest $request, string $invoice): RedirectResponse
    {
        [$context, $id, $detail] = $this->document($request, $invoice);
        $input = $this->input(new SalesInvoiceLineId(new Uuid(Str::uuid()->toString())), $request->validated(), $detail->currency());

        return $this->redirect($context, $id, $this->addLine->execute($context->administration->id(), $id, $input), 'Factuurregel toegevoegd.');
    }

    public function update(SalesInvoiceLineRequest $request, string $invoice, string $line): RedirectResponse
    {
        [$context, $id, $detail] = $this->document($request, $invoice);
        $lineId = $this->lineId($line);
        abort_unless($this->contains($detail->lines(), $lineId), 404);
        $input = $this->input($lineId, $request->validated(), $detail->currency());

        return $this->redirect($context, $id, $this->updateLine->execute($context->administration->id(), $id, $input), 'Factuurregel bijgewerkt.');
    }

    public function destroy(Request $request, string $invoice, string $line): RedirectResponse
    {
        [$context, $id, $detail] = $this->document($request, $invoice);
        $lineId = $this->lineId($line);
        abort_unless($this->contains($detail->lines(), $lineId), 404);

        return $this->redirect($context, $id, $this->removeLine->execute($context->administration->id(), $id, $lineId), 'Factuurregel verwijderd.');
    }

    /** @param array<string, string> $input */
    private function input(SalesInvoiceLineId $id, array $input, $currency): SalesInvoiceLineInput
    {
        return new SalesInvoiceLineInput($id, new LineDescription($input['description']), new Quantity($input['quantity']), new Money($input['unit_price'], $currency), new TaxCodeId(new Uuid($input['tax_code_id'])));
    }

    /** @return array{ActiveAdministrationContext, SalesInvoiceId, SalesInvoiceDetail} */
    private function document(Request $request, string $invoice): array
    {
        /** @var ActiveAdministrationContext $context */
        $context = $request->attributes->get('administration_context');
        $id = $this->invoiceId($invoice);
        $detail = $this->details->find($context->administration->id(), $id);
        abort_if($detail === null, 404);

        return [$context, $id, $detail];
    }

    private function invoiceId(string $value): SalesInvoiceId
    {
        try {
            return new SalesInvoiceId(new Uuid($value));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    private function lineId(string $value): SalesInvoiceLineId
    {
        try {
            return new SalesInvoiceLineId(new Uuid($value));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    private function contains(array $lines, SalesInvoiceLineId $id): bool
    {
        foreach ($lines as $line) {
            if ($line->id()->equals($id)) {
                return true;
            }
        }

        return false;
    }

    private function redirect(ActiveAdministrationContext $context, SalesInvoiceId $id, SalesInvoiceWriteResult $result, string $message): RedirectResponse
    {
        if ($result === SalesInvoiceWriteResult::NotFound) {
            abort(404);
        }
        if ($result !== SalesInvoiceWriteResult::Success) {
            $error = match ($result) {
                SalesInvoiceWriteResult::TaxCodeNotFound, SalesInvoiceWriteResult::TaxCodeInactive, SalesInvoiceWriteResult::WrongTaxDirection => 'De geselecteerde btw-code is niet beschikbaar.',
                SalesInvoiceWriteResult::TaxCalculationFailure => 'Deze regel kan niet exact zonder afronding worden berekend.',
                SalesInvoiceWriteResult::CustomerVatIdMissing => 'Voor deze fiscale behandeling ontbreekt het btw-identificatienummer van de klant.',
                SalesInvoiceWriteResult::CustomerJurisdictionMissing => 'Voor deze fiscale behandeling ontbreekt de fiscale jurisdictie van de klant.',
                SalesInvoiceWriteResult::SupplierVatIdMissing => 'Vul eerst het btw-identificatienummer van de administratie in.',
                SalesInvoiceWriteResult::SupplierJurisdictionMissing => 'Vul eerst de fiscale jurisdictie van de administratie in.',
                SalesInvoiceWriteResult::SupplyDateMissing => 'Vul voor deze fiscale behandeling een prestatiedatum in.',
                default => 'De actie is niet toegestaan in de huidige factuurstatus.',
            };

            return back()->withInput()->withErrors(['tax_code_id' => $error]);
        }

        return $this->permissions->allows($context->permissionIds, SalesPermission::View->id())
            ? redirect()->route('sales.invoices.show', $id->toString())->with('status', $message)
            : redirect()->route('app')->with('status', $message);
    }
}
