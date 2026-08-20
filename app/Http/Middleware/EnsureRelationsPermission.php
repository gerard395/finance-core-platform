<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Identity\PermissionAuthorizer;
use App\Domain\Identity\Definitions\RelationsPermission;
use App\Http\Administration\ActiveAdministrationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use ValueError;

final readonly class EnsureRelationsPermission
{
    public function __construct(private PermissionAuthorizer $authorizer) {}

    public function handle(Request $request, Closure $next, string $permissionCode): Response
    {
        $context = $request->attributes->get('administration_context');

        try {
            $permission = RelationsPermission::from($permissionCode);
        } catch (ValueError) {
            abort(Response::HTTP_FORBIDDEN);
        }

        if (! $context instanceof ActiveAdministrationContext
            || ! $this->authorizer->allows($context->permissionIds, $permission->id())) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    public static function using(RelationsPermission $permission): string
    {
        return self::class.':'.$permission->value;
    }
}
