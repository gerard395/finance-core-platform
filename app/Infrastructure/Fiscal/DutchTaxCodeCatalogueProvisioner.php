<?php

declare(strict_types=1);

namespace App\Infrastructure\Fiscal;

use App\Application\Fiscal\TaxCodeCatalogueProvisioner;
use App\Application\Fiscal\TaxCodeCatalogueProvisioningConflict;
use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Enums\IcpClassification;
use App\Domain\Fiscal\Enums\TaxCodeStatus;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\Enums\TaxTreatment;
use App\Domain\Fiscal\Enums\VatReturnClassification;
use App\Domain\Fiscal\ValueObjects\TaxRate;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use stdClass;

final readonly class DutchTaxCodeCatalogueProvisioner implements TaxCodeCatalogueProvisioner
{
    private const string ID_NAMESPACE = 'b937274e-20a9-4a89-80d6-f247f342d4c2';

    /** @var array<string, array{name: string, rate: string, treatment: TaxTreatment, vat: VatReturnClassification, icp: IcpClassification}> */
    private const array DEFINITIONS = [
        'BTW21' => ['name' => 'BTW hoog (algemeen tarief)', 'rate' => '21', 'treatment' => TaxTreatment::DomesticStandard, 'vat' => VatReturnClassification::DomesticStandard, 'icp' => IcpClassification::None],
        'BTW9' => ['name' => 'BTW laag (verlaagd tarief)', 'rate' => '9', 'treatment' => TaxTreatment::DomesticReduced, 'vat' => VatReturnClassification::DomesticReduced, 'icp' => IcpClassification::None],
        'BTW0' => ['name' => 'BTW 0%', 'rate' => '0', 'treatment' => TaxTreatment::ZeroRated, 'vat' => VatReturnClassification::DomesticZeroRated, 'icp' => IcpClassification::None],
    ];

    public function __construct(private TransactionManager $transactions) {}

    public function ensureDutchBasicOutputForAdministration(AdministrationId $administrationId): void
    {
        $this->transactions->run(function () use ($administrationId): void {
            foreach (self::DEFINITIONS as $code => $definition) {
                $existing = DB::table('tax_codes')
                    ->where('administration_id', $administrationId->toString())
                    ->where('code', $code)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    $this->assertCompatible($existing, $code, $definition);

                    continue;
                }

                $id = Uuid::uuid5(self::ID_NAMESPACE, $administrationId->toString().':'.$code)->toString();
                if (DB::table('tax_codes')->where('id', $id)->exists()) {
                    throw new TaxCodeCatalogueProvisioningConflict(
                        "Stable TaxCode identity for {$code} is already in use.",
                    );
                }

                $now = now();
                DB::table('tax_codes')->insert([
                    'id' => $id,
                    'administration_id' => $administrationId->toString(),
                    'code' => $code,
                    'name' => $definition['name'],
                    'rate' => $definition['rate'],
                    'direction' => TaxPostingDirection::Output->value,
                    'status' => TaxCodeStatus::Active->value,
                    'treatment' => $definition['treatment']->value,
                    'vat_return_classification' => $definition['vat']->value,
                    'icp_classification' => $definition['icp']->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    /** @param array{name: string, rate: string, treatment: TaxTreatment, vat: VatReturnClassification, icp: IcpClassification} $definition */
    private function assertCompatible(stdClass $existing, string $code, array $definition): void
    {
        $existingRate = new TaxRate((string) $existing->rate);
        if ($existingRate->value() !== (new TaxRate($definition['rate']))->value()
            || $existing->direction !== TaxPostingDirection::Output->value
            || $existing->treatment !== $definition['treatment']->value
            || $existing->vat_return_classification !== $definition['vat']->value
            || $existing->icp_classification !== $definition['icp']->value) {
            throw new TaxCodeCatalogueProvisioningConflict(
                "Existing TaxCode {$code} conflicts with the Dutch basic Output catalogue.",
            );
        }
    }
}
