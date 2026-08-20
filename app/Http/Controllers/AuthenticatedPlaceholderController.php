<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Identity\Entities\User as DomainUser;
use App\Http\Administration\ActiveAdministrationContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AuthenticatedPlaceholderController extends Controller
{
    public function __invoke(Request $request): View
    {
        /** @var DomainUser $domainUser */
        $domainUser = $request->attributes->get('domain_user');
        /** @var ActiveAdministrationContext $administrationContext */
        $administrationContext = $request->attributes->get('administration_context');

        return view('app.placeholder', [
            'domainUser' => $domainUser,
            'administrationContext' => $administrationContext,
        ]);
    }
}
