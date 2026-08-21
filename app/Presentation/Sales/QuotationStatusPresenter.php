<?php

declare(strict_types=1);

namespace App\Presentation\Sales;

use App\Domain\Sales\Enums\QuotationStatus;

final class QuotationStatusPresenter
{
    public static function label(QuotationStatus $status): string
    {
        return match ($status) {
            QuotationStatus::Draft => 'Concept',
            QuotationStatus::Sent => 'Verzonden',
            QuotationStatus::Accepted => 'Geaccepteerd',
            QuotationStatus::Rejected => 'Afgewezen',
            QuotationStatus::Expired => 'Verlopen',
        };
    }
}
