<?php

declare(strict_types=1);

namespace App\Application\Banking;

enum ReconcileBankStatementEntryStatus: string
{
    case Success = 'success';
    case NotFound = 'not_found';
    case Ignored = 'ignored';
    case AlreadyReconciled = 'already_reconciled';
    case InvalidIntent = 'invalid_intent';
    case InvalidAllocation = 'invalid_allocation';
    case AllocationIncomplete = 'allocation_incomplete';
    case AllocationExceedsOpenBalance = 'allocation_exceeds_open_balance';
    case RelationMismatch = 'relation_mismatch';
    case MissingPostingConfiguration = 'missing_posting_configuration';
    case InvalidContraAccount = 'invalid_contra_account';
    case PeriodClosed = 'period_closed';
    case NoAccountingPeriod = 'no_accounting_period';
    case PeriodIntegrityFailure = 'period_integrity_failure';
    case FinancialStateInvalid = 'financial_state_invalid';
    case PostingFailure = 'posting_failure';
    case ConcurrencyConflict = 'concurrency_conflict';
}
