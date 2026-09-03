<?php

declare(strict_types=1);

namespace App\Application\Banking;

enum BankStatementParseStatus: string
{
    case Success = 'success';
    case UnsupportedFormat = 'unsupported_format';
    case UnsupportedVersion = 'unsupported_version';
    case UnsupportedCurrency = 'unsupported_currency';
    case UnsupportedEntryStructure = 'unsupported_entry_structure';
    case MalformedFile = 'malformed_file';
    case SecurityViolation = 'security_violation';
    case BankAccountMismatch = 'bank_account_mismatch';
    case IntegrityFailure = 'integrity_failure';
}
