<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Identity\Entities\User as DomainUser;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AuthenticatedPlaceholderController extends Controller
{
    public function __invoke(Request $request): View
    {
        /** @var DomainUser $domainUser */
        $domainUser = $request->attributes->get('domain_user');

        return view('app.placeholder', ['domainUser' => $domainUser]);
    }
}
