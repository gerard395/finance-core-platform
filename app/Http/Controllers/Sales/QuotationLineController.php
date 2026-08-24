<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Application\Identity\PermissionAuthorizer;
use App\Application\Sales\AddQuotationLine;
use App\Application\Sales\QuotationDetailReadRepository;
use App\Application\Sales\QuotationWriteResult;
use App\Application\Sales\RemoveQuotationLine;
use App\Application\Sales\UpdateQuotationLine;
use App\Domain\Identity\Definitions\SalesPermission;
use App\Domain\Sales\Entities\QuotationLine;
use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Sales\ValueObjects\QuotationLineId;
use App\Domain\Shared\Commerce\ValueObjects\LineDescription;
use App\Domain\Shared\Commerce\ValueObjects\Quantity;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\QuotationLineRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class QuotationLineController extends Controller
{
    public function __construct(
        private readonly QuotationDetailReadRepository $details,
        private readonly AddQuotationLine $addLine,
        private readonly UpdateQuotationLine $updateLine,
        private readonly RemoveQuotationLine $removeLine,
        private readonly PermissionAuthorizer $permissions,
    ) {}

    public function store(QuotationLineRequest $request, string $quotation): RedirectResponse
    {
        [$context, $id, $detail] = $this->document($request, $quotation);
        $line = $this->line(new QuotationLineId(new Uuid(Str::uuid()->toString())), $request->validated(), $detail->currency());

        return $this->redirect($context, $id, $this->addLine->execute($context->administration->id(), $id, $line), 'Regel toegevoegd.');
    }

    public function update(QuotationLineRequest $request, string $quotation, string $line): RedirectResponse
    {
        [$context, $id, $detail] = $this->document($request, $quotation);
        $lineId = $this->lineId($line);
        abort_unless($this->contains($detail->lines(), $lineId), 404);
        $replacement = $this->line($lineId, $request->validated(), $detail->currency());

        return $this->redirect($context, $id, $this->updateLine->execute($context->administration->id(), $id, $replacement), 'Regel bijgewerkt.');
    }

    public function destroy(Request $request, string $quotation, string $line): RedirectResponse
    {
        [$context, $id, $detail] = $this->document($request, $quotation);
        $lineId = $this->lineId($line);
        abort_unless($this->contains($detail->lines(), $lineId), 404);

        return $this->redirect($context, $id, $this->removeLine->execute($context->administration->id(), $id, $lineId), 'Regel verwijderd.');
    }

    private function line(QuotationLineId $id, array $input, $currency): QuotationLine
    {
        return new QuotationLine($id, new LineDescription($input['description']), new Quantity($input['quantity']), new Money($input['unit_price'], $currency));
    }

    private function document(Request $request, string $quotation): array
    {
        /** @var ActiveAdministrationContext $context */
        $context = $request->attributes->get('administration_context');
        $id = $this->quotationId($quotation);
        $detail = $this->details->find($context->administration->id(), $id);
        abort_if($detail === null, 404);

        return [$context, $id, $detail];
    }

    private function quotationId(string $value): QuotationId
    {
        try {
            return new QuotationId(new Uuid($value));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    private function lineId(string $value): QuotationLineId
    {
        try {
            return new QuotationLineId(new Uuid($value));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    private function contains(array $lines, QuotationLineId $id): bool
    {
        foreach ($lines as $line) {
            if ($line->id()->equals($id)) {
                return true;
            }
        }

        return false;
    }

    private function redirect(ActiveAdministrationContext $context, QuotationId $id, QuotationWriteResult $result, string $message): RedirectResponse
    {
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
