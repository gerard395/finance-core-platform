<?php

declare(strict_types=1);

namespace App\Http\Controllers\Relations;

use App\Application\Identity\PermissionAuthorizer;
use App\Application\Relations\CreateRelation;
use App\Application\Relations\RelationWriteResult;
use App\Domain\Identity\Definitions\RelationsPermission;
use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Relations\ValueObjects\RelationCode;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Relations\StoreRelationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;

final class RelationCreateController extends Controller
{
    public function __construct(
        private readonly CreateRelation $createRelation,
        private readonly PermissionAuthorizer $permissionAuthorizer,
    ) {}

    public function create(Request $request): View
    {
        $context = $this->context($request);

        return view('relations.create', $this->viewData($context));
    }

    public function store(StoreRelationRequest $request): RedirectResponse
    {
        $context = $this->context($request);
        $validated = $request->validated();
        $relationId = new RelationId(new Uuid(Str::uuid()->toString()));

        try {
            $result = $this->createRelation->execute(
                $context->administration->id(),
                $relationId,
                new RelationCode($validated['code']),
                new DisplayName($validated['name']),
            );
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['code' => 'De relatiegegevens zijn ongeldig.']);
        }

        if ($result === RelationWriteResult::DuplicateCode) {
            return back()->withInput()->withErrors(['code' => 'Deze relatiecode is al in gebruik.']);
        }

        if ($result !== RelationWriteResult::Success) {
            return back()->withInput()->withErrors(['code' => 'De relatie kon niet worden aangemaakt. Probeer het opnieuw.']);
        }

        if ($this->can($context, RelationsPermission::View)) {
            return redirect()->route('relations.show', $relationId->toString())->with('status', 'Relatie aangemaakt.');
        }

        return redirect()->route('app')->with('status', 'Relatie aangemaakt.');
    }

    /** @return array<string, mixed> */
    private function viewData(ActiveAdministrationContext $context): array
    {
        return [
            'domainUser' => $context->user,
            'administrationContext' => $context,
            'canViewRelations' => $this->can($context, RelationsPermission::View),
        ];
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
