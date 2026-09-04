<?php

declare(strict_types=1);

namespace App\Domain\Banking\Enums;

enum BankEntryReconciliationIntent: string
{
    case CustomerReceipt = 'customer_receipt';
    case SupplierPayment = 'supplier_payment';
    case Other = 'other';
}
