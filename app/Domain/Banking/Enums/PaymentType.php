<?php

declare(strict_types=1);

namespace App\Domain\Banking\Enums;

enum PaymentType: string
{
    case CustomerReceipt = 'customer_receipt';
    case SupplierPayment = 'supplier_payment';
}
