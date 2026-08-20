<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Identity\Entities\User;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\AdministrationAccessResolver;
use App\Http\Middleware\EnsureActiveAdministration;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

final class AdministrationSelectionController extends Controller
{
    public function create(Request $request, AdministrationAccessResolver $resolver): View
    {
        /** @var User $user */
        $user = $request->attributes->get('domain_user');

        return view('administrations.select', [
            'domainUser' => $user,
            'administrations' => $resolver->accessibleAdministrations($user, new DateTimeImmutable),
        ]);
    }

    public function store(Request $request, AdministrationAccessResolver $resolver): RedirectResponse
    {
        $validated = $request->validate(['administration_id' => ['required', 'string']]);
        /** @var User $user */
        $user = $request->attributes->get('domain_user');

        try {
            $id = new AdministrationId(new Uuid($validated['administration_id']));
        } catch (InvalidArgumentException) {
            throw $this->invalidSelection();
        }

        if ($resolver->resolve($user, $id, new DateTimeImmutable) === null) {
            throw $this->invalidSelection();
        }

        $request->session()->put(EnsureActiveAdministration::SESSION_KEY, $id->toString());

        return redirect()->route('app');
    }

    private function invalidSelection(): ValidationException
    {
        return ValidationException::withMessages([
            'administration_id' => 'De geselecteerde administratie is niet beschikbaar.',
        ]);
    }
}
