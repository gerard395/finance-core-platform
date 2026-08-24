<?php

use App\Application\Development\DevelopmentAccountingMasterDataProvisioner;
use App\Application\Development\DevelopmentAccountingMasterDataProvisioningConflict;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Shared\Identity\Uuid;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('development:provision-demo-accounting {administration}', function (DevelopmentAccountingMasterDataProvisioner $provisioner): int {
    try {
        $result = $provisioner->provision(new AdministrationId(new Uuid($this->argument('administration'))));
    } catch (InvalidArgumentException|DevelopmentAccountingMasterDataProvisioningConflict $exception) {
        $this->error($exception->getMessage());

        return Command::FAILURE;
    }

    $this->table(['Type', 'Code', 'ID'], [
        ['Sales Journal', $result->salesJournal->code()->value(), $result->salesJournal->id()->toString()],
        ['Accounts Receivable', $result->accountsReceivable->code()->value(), $result->accountsReceivable->id()->toString()],
        ['Revenue', $result->revenue->code()->value(), $result->revenue->id()->toString()],
        ['Output VAT', $result->outputVat->code()->value(), $result->outputVat->id()->toString()],
    ]);
    $this->info('Development Sales posting configuration is ready.');

    return Command::SUCCESS;
})->purpose('Provision explicit demo-only accounting master data for one Administration');
