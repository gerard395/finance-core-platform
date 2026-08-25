<?php

declare(strict_types=1);

namespace App\View\Components\Layouts;

use App\Application\Identity\PermissionAuthorizer;
use App\Domain\Identity\Definitions\AdministrationPermission;
use App\Domain\Identity\Definitions\PurchasingPermission;
use App\Domain\Identity\Definitions\RelationsPermission;
use App\Domain\Identity\Definitions\SalesPermission;
use App\Domain\Identity\Entities\User;
use App\Http\Administration\ActiveAdministrationContext;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

final class App extends Component
{
    public readonly bool $canViewRelations;

    public readonly bool $canViewSales;

    public readonly bool $canUpdateAdministrationSettings;

    public readonly bool $canViewPurchasing;

    public function __construct(
        public readonly User $domainUser,
        public readonly ActiveAdministrationContext $administrationContext,
        public readonly string $title,
        PermissionAuthorizer $permissionAuthorizer,
    ) {
        $this->canViewRelations = $permissionAuthorizer->allows(
            $administrationContext->permissionIds,
            RelationsPermission::View->id(),
        );
        $this->canViewSales = $permissionAuthorizer->allows(
            $administrationContext->permissionIds,
            SalesPermission::View->id(),
        );
        $this->canViewPurchasing = $permissionAuthorizer->allows(
            $administrationContext->permissionIds,
            PurchasingPermission::View->id(),
        );
        $this->canUpdateAdministrationSettings = $permissionAuthorizer->allows(
            $administrationContext->permissionIds,
            AdministrationPermission::UpdateSettings->id(),
        );
    }

    public function render(): View
    {
        return view('components.layouts.app');
    }
}
