<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Application\Sales\DeliveryOutcomeResolutionStatus;
use App\Application\Sales\ResolveUnknownDeliveryOutcome;
use App\Domain\Sales\Enums\DeliveryOutcomeResolutionType;
use App\Domain\Sales\ValueObjects\DeliveryAttemptId;
use App\Domain\Sales\ValueObjects\DeliveryOutcomeResolutionId;
use App\Domain\Sales\ValueObjects\DeliveryRequestId;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class DeliveryOutcomeResolutionController extends Controller
{
    public function __construct(private readonly ResolveUnknownDeliveryOutcome $resolve) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'resolution_id' => ['required', 'uuid'], 'delivery_request_id' => ['required', 'uuid'], 'delivery_attempt_id' => ['required', 'uuid'],
            'resolution_type' => ['required', Rule::enum(DeliveryOutcomeResolutionType::class)], 'reason' => ['nullable', 'string', 'max:500'],
        ]);
        /** @var ActiveAdministrationContext $context */
        $context = $request->attributes->get('administration_context');
        $result = $this->resolve->execute(
            new DeliveryOutcomeResolutionId(new Uuid($validated['resolution_id'])), $context->administration->id(),
            new DeliveryRequestId(new Uuid($validated['delivery_request_id'])), new DeliveryAttemptId(new Uuid($validated['delivery_attempt_id'])),
            DeliveryOutcomeResolutionType::from($validated['resolution_type']), $context->user->id(), $validated['reason'] ?? null,
        );
        if ($result === DeliveryOutcomeResolutionStatus::NotFound) {
            abort(404);
        }
        if ($result === DeliveryOutcomeResolutionStatus::Unauthorized) {
            abort(403);
        }
        if (! in_array($result, [DeliveryOutcomeResolutionStatus::Resolved, DeliveryOutcomeResolutionStatus::AlreadyResolved], true)) {
            return back()->with('error', 'Deze verzendpoging kan niet worden afgehandeld.');
        }

        return back()->with('status', 'De onzekere verzendstatus is vastgelegd.');
    }
}
