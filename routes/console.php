<?php

use App\Application\Development\DevelopmentAccountingMasterDataProvisioner;
use App\Application\Development\DevelopmentAccountingMasterDataProvisioningConflict;
use App\Application\Sales\DeliveryInfrastructureReadinessStatus;
use App\Application\Sales\DeliveryOutboxStore;
use App\Application\Sales\SalesDocumentDeliveryInfrastructureReadiness;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Shared\Identity\Uuid;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
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

Artisan::command('delivery:health', function (SalesDocumentDeliveryInfrastructureReadiness $readiness): int {
    $result = $readiness->check();
    $rows = [
        ['Environment', app()->environment()], ['Overall', $result->status->value], ['Queue backend', $result->queueBackend],
        ['Queue name', $result->queueName], ['Worker heartbeat age', $result->heartbeatAgeSeconds === null ? 'missing' : $result->heartbeatAgeSeconds.'s'],
    ];
    foreach ($result->counters as $name => $count) {
        $rows[] = [$name, (string) $count];
    }
    $this->table(['Check', 'Value'], $rows);

    return $result->status === DeliveryInfrastructureReadinessStatus::Ready ? Command::SUCCESS : Command::FAILURE;
})->purpose('Report privacy-safe Sales document delivery infrastructure readiness');

Artisan::command('delivery:recover', function (DeliveryOutboxStore $outbox): int {
    $this->info('Recovered pre-send delivery claims: '.$outbox->recoverStalePreSend());

    return Command::SUCCESS;
})->purpose('Recover expired delivery claims that never crossed the transport boundary');

Schedule::command('delivery:recover')->everyMinute()->withoutOverlapping();
