<?php

namespace Database\Seeders;

use App\Infrastructure\Identity\AdministrationAuthorizationProvisioner;
use Illuminate\Database\Seeder;

final class AdministrationAuthorizationSeeder extends Seeder
{
    public function run(AdministrationAuthorizationProvisioner $provisioner): void
    {
        $provisioner->provision();
    }
}
