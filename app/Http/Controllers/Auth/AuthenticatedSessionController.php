<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Auth\DomainUserResolver;
use App\Http\Controllers\Controller;
use App\Models\User as AuthUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request, DomainUserResolver $resolver): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);
        $credentials['email'] = Str::lower($credentials['email']);
        $key = Str::transliterate($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw $this->invalidCredentials();
        }

        if (! Auth::attempt($credentials, false)) {
            RateLimiter::hit($key, 60);
            throw $this->invalidCredentials();
        }

        $authUser = Auth::user();
        $domainUser = $authUser instanceof AuthUser ? $resolver->resolve($authUser) : null;

        if ($domainUser === null || ! $domainUser->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            RateLimiter::hit($key, 60);
            throw $this->invalidCredentials();
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended(route('app'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function invalidCredentials(): ValidationException
    {
        return ValidationException::withMessages([
            'email' => 'De inloggegevens zijn ongeldig.',
        ]);
    }
}
