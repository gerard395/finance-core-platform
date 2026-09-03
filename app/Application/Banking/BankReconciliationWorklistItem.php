<?php

declare(strict_types=1);

namespace App\Application\Banking;

final readonly class BankReconciliationWorklistItem
{
    public function __construct(public BankReconciliationSourceItem $source, public ?BankEntrySuggestion $suggestion) {}
}
