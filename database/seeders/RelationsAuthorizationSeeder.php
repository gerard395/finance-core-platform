<?php

namespace Database\Seeders;

use App\Infrastructure\Identity\RelationsAuthorizationProvisioner;
use Illuminate\Database\Seeder;

final class RelationsAuthorizationSeeder extends Seeder
{
    public function run(RelationsAuthorizationProvisioner $provisioner): void
    {
        $provisioner->provision();
    }
}
