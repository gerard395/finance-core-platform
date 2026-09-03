<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Banking\Enums\BankEntryDirection;
use App\Domain\Banking\ValueObjects\AdministrationBankAccountId;
use App\Domain\Shared\Finance\Money;
use DateTimeImmutable;

final readonly class BankReconciliationWorklistFilter
{
    /** @param list<BankEntryDerivedState> $states */
    public function __construct(public ?AdministrationBankAccountId $bankAccountId = null, public ?DateTimeImmutable $from = null, public ?DateTimeImmutable $to = null, public ?BankEntryDirection $direction = null, public array $states = [BankEntryDerivedState::Unresolved], public ?Money $amount = null, public ?string $search = null) {}
}
