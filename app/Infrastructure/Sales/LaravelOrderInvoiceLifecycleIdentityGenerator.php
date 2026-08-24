<?php

declare(strict_types=1);

namespace App\Infrastructure\Sales;

use App\Application\Sales\OrderInvoiceLifecycleIdentityGenerator;
use App\Domain\Sales\ValueObjects\OrderInvoiceAllocationId;
use App\Domain\Sales\ValueObjects\OrderInvoiceReservationReleaseId;
use App\Domain\Shared\Identity\Uuid;
use Illuminate\Support\Str;

final class LaravelOrderInvoiceLifecycleIdentityGenerator implements OrderInvoiceLifecycleIdentityGenerator
{
    public function allocationId(): OrderInvoiceAllocationId
    {
        return new OrderInvoiceAllocationId(new Uuid(Str::uuid()->toString()));
    }

    public function releaseId(): OrderInvoiceReservationReleaseId
    {
        return new OrderInvoiceReservationReleaseId(new Uuid(Str::uuid()->toString()));
    }
}
