<?php

declare(strict_types=1);

namespace App\Application\Banking;

enum ConfirmBankImportStatus: string
{
    case Success = 'success';
    case NotFound = 'not_found';
    case DuplicateBatch = 'duplicate_batch';
    case DuplicateStatement = 'duplicate_statement';
    case DuplicateEntry = 'duplicate_entry';
    case BankAccountMismatch = 'bank_account_mismatch';
    case StatementBalanceMismatch = 'statement_balance_mismatch';
    case MissingStatementBalance = 'missing_statement_balance';
    case UnsupportedCurrency = 'unsupported_currency';
    case IntegrityFailure = 'integrity_failure';
    case StorageFailure = 'storage_failure';
    case ConcurrencyConflict = 'concurrency_conflict';
}
