<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Auth\DomainUserResolver;
use App\Models\User as AuthUser;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureActiveDomainUser
{
    public function __construct(private DomainUserResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $authUser = Auth::user();
        $domainUser = $authUser instanceof AuthUser ? $this->resolver->resolve($authUser) : null;

        if ($domainUser === null || ! $domainUser->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'De inloggegevens zijn ongeldig.',
            ]);
        }

        $request->attributes->set('domain_user', $domainUser);

        return $next($request);
    }
}
