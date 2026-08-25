<?php

namespace Database\Seeders;

use App\Infrastructure\Identity\DeliveryOperationsAuthorizationProvisioner;
use Illuminate\Database\Seeder;

final class DeliveryOperationsAuthorizationSeeder extends Seeder
{
    public function run(DeliveryOperationsAuthorizationProvisioner $provisioner): void
    {
        $provisioner->provision();
    }
}
