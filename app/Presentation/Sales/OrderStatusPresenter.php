<?php

declare(strict_types=1);

namespace App\Presentation\Sales;

use App\Domain\Sales\Enums\OrderStatus;

final class OrderStatusPresenter
{
    public static function label(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Draft => 'Concept',
            OrderStatus::Confirmed => 'Bevestigd',
            OrderStatus::PartiallyInvoiced => 'Deels gefactureerd',
            OrderStatus::FullyInvoiced => 'Volledig gefactureerd',
            OrderStatus::Cancelled => 'Geannuleerd',
        };
    }
}
