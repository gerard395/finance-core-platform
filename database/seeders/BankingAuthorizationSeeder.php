<?php

namespace Database\Seeders;

use App\Infrastructure\Identity\BankingAuthorizationProvisioner;
use Illuminate\Database\Seeder;

final class BankingAuthorizationSeeder extends Seeder
{
    public function run(BankingAuthorizationProvisioner $provisioner): void
    {
        $provisioner->provision();
    }
}
