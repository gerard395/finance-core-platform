<?php

declare(strict_types=1);

namespace App\Domain\Identity\Definitions;

use App\Domain\Identity\ValueObjects\PermissionCode;
use App\Domain\Identity\ValueObjects\PermissionId;
use App\Domain\Identity\ValueObjects\PermissionName;
use App\Domain\Shared\Identity\Uuid;

enum SalesPermission: string
{
    case View = 'SALES.VIEW';
    case ManageQuotations = 'SALES.QUOTATIONS_MANAGE';
    case ManageOrders = 'SALES.ORDERS_MANAGE';
    case ManageInvoiceDrafts = 'SALES.INVOICES_DRAFT_MANAGE';
    case IssueInvoices = 'SALES.INVOICES_ISSUE';
    case PostInvoices = 'SALES.INVOICES_POST';
    case ManageCreditInvoiceDrafts = 'SALES.CREDIT_INVOICES_DRAFT_MANAGE';
    case IssueCreditInvoices = 'SALES.CREDIT_INVOICES_ISSUE';
    case PostCreditInvoices = 'SALES.CREDIT_INVOICES_POST';

    public function id(): PermissionId
    {
        return new PermissionId(new Uuid(match ($this) {
            self::View => 'd5dc72db-392c-49ee-ac31-7599c5197b5c',
            self::ManageQuotations => '94af26d4-147c-40f5-aaa9-e2557e028f8e',
            self::ManageOrders => 'afeb6618-8721-4efd-9a91-b968c8913cf3',
            self::ManageInvoiceDrafts => '46230cc7-9dc0-41ba-8de3-9cc38f6f43e7',
            self::IssueInvoices => 'f2d82571-430b-4b23-a9e4-4701947b188d',
            self::PostInvoices => '272d5d65-f508-4974-966b-0d4b49098a87',
            self::ManageCreditInvoiceDrafts => '854dc07c-1a30-47d1-b2a4-75821cdadfc4',
            self::IssueCreditInvoices => '3d7cc648-9f07-4baf-86d7-f2a6661231a5',
            self::PostCreditInvoices => '82bcbc84-5769-4d24-b63a-d8accb72dc53',
        }));
    }

    public function code(): PermissionCode
    {
        return new PermissionCode($this->value);
    }

    public function name(): PermissionName
    {
        return new PermissionName(match ($this) {
            self::View => 'View Sales Documents',
            self::ManageQuotations => 'Manage Quotations',
            self::ManageOrders => 'Manage Sales Orders',
            self::ManageInvoiceDrafts => 'Manage Sales Invoice Drafts',
            self::IssueInvoices => 'Issue Sales Invoices',
            self::PostInvoices => 'Post Sales Invoices',
            self::ManageCreditInvoiceDrafts => 'Manage Sales Credit Invoice Drafts',
            self::IssueCreditInvoices => 'Issue Sales Credit Invoices',
            self::PostCreditInvoices => 'Post Sales Credit Invoices',
        });
    }
}
