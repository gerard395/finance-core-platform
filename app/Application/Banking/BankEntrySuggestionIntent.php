<?php

declare(strict_types=1);

namespace App\Application\Banking;

enum BankEntrySuggestionIntent: string
{
    case CustomerReceipt = 'customer_receipt';
    case SupplierPayment = 'supplier_payment';
    case Other = 'other';
}
