<?php

declare(strict_types=1);

namespace App\Http\Controllers\Relations;

use App\Application\Sales\ClearPreferredSalesDocumentRecipient;
use App\Application\Sales\SalesDocumentRecipientPurpose;
use App\Application\Sales\SetPreferredSalesDocumentRecipient;
use App\Application\Sales\SetPreferredSalesDocumentRecipientResult;
use App\Domain\Relations\ValueObjects\ContactId;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Sales\ValueObjects\SalesDocumentRecipientPreferenceId;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Relations\SetSalesDocumentRecipientPreferenceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class SalesDocumentRecipientPreferenceController extends Controller
{
    public function __construct(private readonly SetPreferredSalesDocumentRecipient $set, private readonly ClearPreferredSalesDocumentRecipient $clear) {}

    public function store(SetSalesDocumentRecipientPreferenceRequest $request, string $relation): RedirectResponse
    {
        try {
            $relationId = new RelationId(new Uuid($relation));
            $contactId = new ContactId(new Uuid($request->validated('contact_id')));
        } catch (InvalidArgumentException) {
            abort(404);
        }
        /** @var ActiveAdministrationContext $context */
        $context = $request->attributes->get('administration_context');
        $result = $this->set->execute(new SalesDocumentRecipientPreferenceId(new Uuid(Str::uuid()->toString())), $context->administration->id(), $relationId, SalesDocumentRecipientPurpose::from($request->validated('purpose')), $contactId);
        if ($result === SetPreferredSalesDocumentRecipientResult::InvalidContact) {
            return back()->withErrors(['recipient_preference' => 'Selecteer een actieve contactpersoon met e-mailadres van deze relatie.']);
        }

        return back()->with('status', 'Documentontvanger opgeslagen.');
    }

    public function destroy(Request $request, string $relation, string $purpose): RedirectResponse
    {
        try {
            $relationId = new RelationId(new Uuid($relation));
            $typedPurpose = SalesDocumentRecipientPurpose::from($purpose);
        } catch (InvalidArgumentException|\ValueError) {
            abort(404);
        }
        /** @var ActiveAdministrationContext $context */
        $context = $request->attributes->get('administration_context');
        $this->clear->execute($context->administration->id(), $relationId, $typedPurpose);

        return back()->with('status', 'Documentontvanger verwijderd.');
    }
}
