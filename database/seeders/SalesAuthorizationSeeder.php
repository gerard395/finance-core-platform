<?php

namespace Database\Seeders;

use App\Infrastructure\Identity\SalesAuthorizationProvisioner;
use Illuminate\Database\Seeder;

final class SalesAuthorizationSeeder extends Seeder
{
    public function run(SalesAuthorizationProvisioner $provisioner): void
    {
        $provisioner->provision();
    }
}
