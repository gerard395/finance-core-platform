<?php

declare(strict_types=1);

namespace App\Http\Controllers\Relations;

use App\Application\Identity\PermissionAuthorizer;
use App\Application\Relations\GetRelationDetail;
use App\Domain\Identity\Definitions\RelationsPermission;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

final class RelationShowController extends Controller
{
    public function __construct(
        private readonly GetRelationDetail $getRelationDetail,
        private readonly PermissionAuthorizer $permissionAuthorizer,
    ) {}

    public function __invoke(Request $request, string $relation): View
    {
        try {
            $relationId = new RelationId(new Uuid($relation));
        } catch (InvalidArgumentException) {
            abort(404);
        }

        /** @var ActiveAdministrationContext $context */
        $context = $request->attributes->get('administration_context');
        $detail = $this->getRelationDetail->execute($context->administration->id(), $relationId);

        abort_if($detail === null, 404);

        return view('relations.show', [
            'domainUser' => $context->user,
            'administrationContext' => $context,
            'relation' => $detail,
            'canViewRelations' => $this->permissionAuthorizer->allows($context->permissionIds, RelationsPermission::View->id()),
        ]);
    }
}
