<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Identity;

use App\Application\Identity\PermissionAuthorizer;
use App\Domain\Identity\Definitions\RelationsPermission;
use PHPUnit\Framework\TestCase;

final class PermissionAuthorizerTest extends TestCase
{
    public function test_required_permission_is_allowed_when_present(): void
    {
        self::assertTrue((new PermissionAuthorizer)->allows(
            [RelationsPermission::View->id()],
            RelationsPermission::View->id(),
        ));
    }

    public function test_missing_or_wrong_permission_is_denied(): void
    {
        $authorizer = new PermissionAuthorizer;

        self::assertFalse($authorizer->allows([], RelationsPermission::View->id()));
        self::assertFalse($authorizer->allows(
            [RelationsPermission::Create->id()],
            RelationsPermission::View->id(),
        ));
    }

    public function test_multiple_effective_permissions_are_compared_by_stable_identity(): void
    {
        self::assertTrue((new PermissionAuthorizer)->allows(
            [
                RelationsPermission::View->id(),
                RelationsPermission::Create->id(),
                RelationsPermission::ClassifySupplier->id(),
            ],
            RelationsPermission::ClassifySupplier->id(),
        ));
    }
}
