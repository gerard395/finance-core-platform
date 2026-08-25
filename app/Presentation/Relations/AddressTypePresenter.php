<?php

declare(strict_types=1);

namespace App\Presentation\Relations;

use App\Domain\Relations\Enums\AddressType;

final class AddressTypePresenter
{
    public static function label(AddressType $type): string
    {
        return match ($type) {
            AddressType::Visiting => 'Bezoekadres',
            AddressType::Postal => 'Postadres',
            AddressType::Invoice => 'Factuuradres',
            AddressType::Delivery => 'Afleveradres',
            AddressType::Quotation => 'Offerteadres',
        };
    }
}
