<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

enum PurchasePostingConfigurationInvalidReference: string
{
    case PurchaseJournal = 'purchase_journal';
    case AccountsPayable = 'accounts_payable';
    case InputVat = 'input_vat';
}
