<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Identity\Entities\User;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\AdministrationAccessResolver;
use Closure;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureActiveAdministration
{
    public const SESSION_KEY = 'active_administration_id';

    public function __construct(private AdministrationAccessResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $user = $request->attributes->get('domain_user');
        $value = $request->session()->get(self::SESSION_KEY);

        try {
            $id = is_string($value) ? new AdministrationId(new Uuid($value)) : null;
        } catch (InvalidArgumentException) {
            $id = null;
        }

        $context = $user instanceof User && $id !== null
            ? $this->resolver->resolve($user, $id, new DateTimeImmutable)
            : null;

        if ($context === null) {
            $request->session()->forget(self::SESSION_KEY);

            return redirect()->route('administrations.select');
        }

        $request->attributes->set('administration_context', $context);

        return $next($request);
    }
}
