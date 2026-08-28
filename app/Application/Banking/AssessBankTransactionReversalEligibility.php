<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Accounting\Enums\OpenItemSettlementType;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Enums\BankTransactionStatus;
use App\Domain\Banking\ValueObjects\BankTransactionId;

final readonly class AssessBankTransactionReversalEligibility
{
    public function __construct(private BankTransactionReversalSourceReader $sources) {}

    public function execute(AdministrationId $administrationId, BankTransactionId $bankTransactionId): BankTransactionReversalEligibilityStatus
    {
        $source = $this->sources->read($administrationId, $bankTransactionId);
        if ($source === null) {
            return BankTransactionReversalEligibilityStatus::NotFound;
        }

        return $this->forSource($source);
    }

    public function forSource(BankTransactionReversalSource $source): BankTransactionReversalEligibilityStatus
    {
        if ($source->reversal !== null) {
            return $source->financialGraphCoherent
                ? BankTransactionReversalEligibilityStatus::AlreadyReversed
                : BankTransactionReversalEligibilityStatus::FinancialStateInvalid;
        }
        if ($source->transaction->status() !== BankTransactionStatus::Posted) {
            return BankTransactionReversalEligibilityStatus::NotPosted;
        }
        $allocations = $source->transaction->payment()->allocations();
        if (! $source->financialGraphCoherent || $source->posting === null || $source->journalEntry === null || $allocations === [] || count($allocations) !== count($source->settlements)) {
            return BankTransactionReversalEligibilityStatus::FinancialStateInvalid;
        }
        foreach ($source->settlements as $settlement) {
            if ($settlement->settlement->type() !== OpenItemSettlementType::Applied || $settlement->hasReversal) {
                return BankTransactionReversalEligibilityStatus::FinancialStateInvalid;
            }
        }

        return BankTransactionReversalEligibilityStatus::Eligible;
    }
}
