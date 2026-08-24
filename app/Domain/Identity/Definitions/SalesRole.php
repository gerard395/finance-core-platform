<?php

declare(strict_types=1);

namespace App\Domain\Identity\Definitions;

use App\Domain\Identity\ValueObjects\RoleCode;
use App\Domain\Identity\ValueObjects\RoleId;
use App\Domain\Identity\ValueObjects\RoleName;
use App\Domain\Shared\Identity\Uuid;

enum SalesRole: string
{
    case Viewer = 'SALES_VIEWER';
    case Editor = 'SALES_EDITOR';
    case Manager = 'SALES_MANAGER';
    case Poster = 'SALES_POSTER';

    public function id(): RoleId
    {
        return new RoleId(new Uuid(match ($this) {
            self::Viewer => '69a2fd6d-3660-47a5-a5da-e902a6313acf',
            self::Editor => 'daf61074-ae86-4f87-ab50-9d2afaec3281',
            self::Manager => 'bd2b8676-25bc-4399-bb7b-966ed32aeb36',
            self::Poster => '3496dda5-7528-4c2a-8dfa-e97751536f6e',
        }));
    }

    public function code(): RoleCode
    {
        return new RoleCode($this->value);
    }

    public function name(): RoleName
    {
        return new RoleName(match ($this) {
            self::Viewer => 'Sales Viewer',
            self::Editor => 'Sales Editor',
            self::Manager => 'Sales Manager',
            self::Poster => 'Sales Poster',
        });
    }

    /** @return list<SalesPermission> */
    public function permissions(): array
    {
        $editor = [
            SalesPermission::View,
            SalesPermission::ManageQuotations,
            SalesPermission::ManageOrders,
            SalesPermission::ManageInvoiceDrafts,
            SalesPermission::ManageCreditInvoiceDrafts,
        ];

        return match ($this) {
            self::Viewer => [SalesPermission::View],
            self::Editor => $editor,
            self::Manager => [...$editor, SalesPermission::IssueInvoices, SalesPermission::IssueCreditInvoices],
            self::Poster => [SalesPermission::PostInvoices, SalesPermission::PostCreditInvoices],
        };
    }
}
