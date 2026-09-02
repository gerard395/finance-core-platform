<?php

declare(strict_types=1);

namespace App\Application\Fiscal;

enum TaxTreatmentDefinitionSelectionStatus
{
    case Found;
    case Missing;
    case IntegrityFailure;
}
