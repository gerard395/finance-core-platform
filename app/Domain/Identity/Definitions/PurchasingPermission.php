<?php

declare(strict_types=1);

namespace App\Domain\Identity\Definitions;

use App\Domain\Identity\ValueObjects\PermissionCode;
use App\Domain\Identity\ValueObjects\PermissionId;
use App\Domain\Identity\ValueObjects\PermissionName;
use App\Domain\Shared\Identity\Uuid;

enum PurchasingPermission: string
{
    case View = 'PURCHASING.VIEW';
    case ManageInvoiceDrafts = 'PURCHASING.INVOICES_DRAFT_MANAGE';
    case FinalizeInvoices = 'PURCHASING.INVOICES_FINALIZE';
    case PostInvoices = 'PURCHASING.INVOICES_POST';
    case ManageCreditDrafts = 'PURCHASING.CREDITS_DRAFT_MANAGE';
    case FinalizeCredits = 'PURCHASING.CREDITS_FINALIZE';
    case PostCredits = 'PURCHASING.CREDITS_POST';

    public function id(): PermissionId
    {
        return new PermissionId(new Uuid(match ($this) {
            self::View => '6e854eb8-7cc4-4c61-8328-6aa41cb0ac01',
            self::ManageInvoiceDrafts => '0f950710-7de5-42e7-a716-08ae09b17b5c',
            self::FinalizeInvoices => '7593b1f9-39b1-480e-ab8b-46792141d4bb',
            self::PostInvoices => 'c4926113-c69a-49b2-94a3-0f98bcaee9b3',
            self::ManageCreditDrafts => '3a85b19c-8196-47bb-90e2-94c4aa72c101',
            self::FinalizeCredits => '3a85b19c-8196-47bb-90e2-94c4aa72c102',
            self::PostCredits => '3a85b19c-8196-47bb-90e2-94c4aa72c103',
        }));
    }

    public function code(): PermissionCode
    {
        return new PermissionCode($this->value);
    }

    public function name(): PermissionName
    {
        return new PermissionName(match ($this) {
            self::View => 'View Purchase Invoices',
            self::ManageInvoiceDrafts => 'Manage Purchase Invoice Drafts',
            self::FinalizeInvoices => 'Finalize Purchase Invoices',
            self::PostInvoices => 'Post Purchase Invoices',
            self::ManageCreditDrafts => 'Manage Purchase Credit Drafts',
            self::FinalizeCredits => 'Finalize Purchase Credits',
            self::PostCredits => 'Post Purchase Credits',
        });
    }
}
