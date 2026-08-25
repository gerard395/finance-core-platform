<?php

namespace Database\Seeders;

use App\Infrastructure\Identity\PurchasingAuthorizationProvisioner;
use Illuminate\Database\Seeder;

final class PurchasingAuthorizationSeeder extends Seeder
{
    public function run(PurchasingAuthorizationProvisioner $provisioner): void
    {
        $provisioner->provision();
    }
}
