<?php

declare(strict_types=1);

namespace App\Http\Controllers\Relations;

use App\Application\Identity\PermissionAuthorizer;
use App\Application\Relations\ActivateCustomerClassification;
use App\Application\Relations\ActivateSupplierClassification;
use App\Application\Relations\DeactivateCustomerClassification;
use App\Application\Relations\DeactivateSupplierClassification;
use App\Application\Relations\RelationClassificationMutationResult;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Identity\Definitions\RelationsPermission;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class RelationClassificationController extends Controller
{
    public function __construct(
        private readonly ActivateCustomerClassification $activateCustomer,
        private readonly DeactivateCustomerClassification $deactivateCustomer,
        private readonly ActivateSupplierClassification $activateSupplier,
        private readonly DeactivateSupplierClassification $deactivateSupplier,
        private readonly PermissionAuthorizer $permissionAuthorizer,
    ) {}

    public function storeCustomer(Request $request, string $relation): RedirectResponse
    {
        return $this->handle($request, $this->relationId($relation), $this->activateCustomer->execute(...), 'Klantclassificatie is geactiveerd.');
    }

    public function destroyCustomer(Request $request, string $relation): RedirectResponse
    {
        return $this->handle($request, $this->relationId($relation), $this->deactivateCustomer->execute(...), 'Klantclassificatie is gedeactiveerd.');
    }

    public function storeSupplier(Request $request, string $relation): RedirectResponse
    {
        return $this->handle($request, $this->relationId($relation), $this->activateSupplier->execute(...), 'Leveranciersclassificatie is geactiveerd.');
    }

    public function destroySupplier(Request $request, string $relation): RedirectResponse
    {
        return $this->handle($request, $this->relationId($relation), $this->deactivateSupplier->execute(...), 'Leveranciersclassificatie is gedeactiveerd.');
    }

    /** @param callable(AdministrationId, RelationId): RelationClassificationMutationResult $operation */
    private function handle(Request $request, RelationId $relationId, callable $operation, string $message): RedirectResponse
    {
        $context = $this->context($request);
        $result = $operation($context->administration->id(), $relationId);

        if ($result === RelationClassificationMutationResult::NotFound) {
            abort(404);
        }

        $destination = $this->permissionAuthorizer->allows($context->permissionIds, RelationsPermission::View->id())
            ? redirect()->route('relations.show', $relationId->toString())
            : redirect()->route('app');

        if ($result === RelationClassificationMutationResult::Success) {
            return $destination->with('status', $message);
        }

        $error = match ($result) {
            RelationClassificationMutationResult::NoClassification => 'Deze classificatie bestaat niet.',
            RelationClassificationMutationResult::SequenceMissing => 'De nummerreeks voor deze classificatie ontbreekt.',
            RelationClassificationMutationResult::SequenceInactive => 'De nummerreeks voor deze classificatie is niet actief.',
            RelationClassificationMutationResult::PersistenceConflict => 'De classificatie kon niet worden opgeslagen. Probeer het opnieuw.',
            RelationClassificationMutationResult::NotFound, RelationClassificationMutationResult::Success => throw new \LogicException('Handled classification result reached failure mapping.'),
        };

        return $destination->with('error', $error);
    }

    private function relationId(string $value): RelationId
    {
        try {
            return new RelationId(new Uuid($value));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    private function context(Request $request): ActiveAdministrationContext
    {
        /** @var ActiveAdministrationContext */
        return $request->attributes->get('administration_context');
    }
}
