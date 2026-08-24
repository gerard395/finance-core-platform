<?php

declare(strict_types=1);

namespace App\Http\Controllers\Relations;

use App\Application\Identity\PermissionAuthorizer;
use App\Application\Relations\GetRelationDetail;
use App\Application\Relations\RelationDetail;
use App\Application\Relations\RelationWriteResult;
use App\Application\Relations\UpdateRelation;
use App\Domain\Identity\Definitions\RelationsPermission;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Fiscal\VatIdentificationNumber;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Relations\UpdateRelationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

final class RelationEditController extends Controller
{
    public function __construct(
        private readonly GetRelationDetail $getRelationDetail,
        private readonly UpdateRelation $updateRelation,
        private readonly PermissionAuthorizer $permissionAuthorizer,
    ) {}

    public function edit(Request $request, string $relation): View
    {
        $context = $this->context($request);
        $detail = $this->detail($context, $this->relationId($relation));

        return view('relations.edit', [
            'domainUser' => $context->user,
            'administrationContext' => $context,
            'relation' => $detail,
            'canViewRelations' => $this->can($context, RelationsPermission::View),
        ]);
    }

    public function update(UpdateRelationRequest $request, string $relation): RedirectResponse
    {
        $context = $this->context($request);
        $relationId = $this->relationId($relation);
        $validated = $request->validated();

        try {
            $result = $this->updateRelation->execute(
                $context->administration->id(),
                $relationId,
                new DisplayName($validated['name']),
                $validated['status'] === 'active',
                $this->vatId($validated['vat_identification_number'] ?? null),
                $this->jurisdiction($validated['fiscal_jurisdiction'] ?? null),
                true,
            );
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['name' => 'De relatiegegevens zijn ongeldig.']);
        }

        if ($result === RelationWriteResult::NotFound) {
            abort(404);
        }

        if ($result !== RelationWriteResult::Success) {
            return back()->withInput()->withErrors(['name' => 'De relatie kon niet worden opgeslagen. Probeer het opnieuw.']);
        }

        if ($this->can($context, RelationsPermission::View)) {
            return redirect()->route('relations.show', $relationId->toString())->with('status', 'Relatie bijgewerkt.');
        }

        return redirect()->route('app')->with('status', 'Relatie bijgewerkt.');
    }

    private function relationId(string $value): RelationId
    {
        try {
            return new RelationId(new Uuid($value));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    private function vatId(?string $value): ?VatIdentificationNumber
    {
        return $value === null ? null : new VatIdentificationNumber($value);
    }

    private function jurisdiction(?string $value): ?CountryCode
    {
        return $value === null ? null : new CountryCode($value);
    }

    private function detail(ActiveAdministrationContext $context, RelationId $relationId): RelationDetail
    {
        $detail = $this->getRelationDetail->execute($context->administration->id(), $relationId);
        abort_if($detail === null, 404);

        return $detail;
    }

    private function context(Request $request): ActiveAdministrationContext
    {
        /** @var ActiveAdministrationContext */
        return $request->attributes->get('administration_context');
    }

    private function can(ActiveAdministrationContext $context, RelationsPermission $permission): bool
    {
        return $this->permissionAuthorizer->allows($context->permissionIds, $permission->id());
    }
}
