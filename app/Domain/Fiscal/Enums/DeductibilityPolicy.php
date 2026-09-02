<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\Enums;

enum DeductibilityPolicy: string
{
    case NotApplicable = 'not_applicable';
    case FixedFull = 'fixed_full';
    case FixedZero = 'fixed_zero';
    case UserSpecifiedLineRate = 'user_specified_line_rate';
}
