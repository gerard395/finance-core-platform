<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Identity\PermissionAuthorizer;
use App\Domain\Identity\Definitions\PurchasingPermission;
use App\Http\Administration\ActiveAdministrationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use ValueError;

final readonly class EnsurePurchasingPermission
{
    public function __construct(private PermissionAuthorizer $authorizer) {}

    public function handle(Request $request, Closure $next, string $permissionCode): Response
    {
        $context = $request->attributes->get('administration_context');
        try {
            $permission = PurchasingPermission::from($permissionCode);
        } catch (ValueError) {
            abort(403);
        }
        abort_unless($context instanceof ActiveAdministrationContext && $this->authorizer->allows($context->permissionIds, $permission->id()), 403);

        return $next($request);
    }

    public static function using(PurchasingPermission $permission): string
    {
        return self::class.':'.$permission->value;
    }
}
