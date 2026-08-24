<?php

declare(strict_types=1);

namespace App\Infrastructure\Sales;

use App\Application\Sales\SalesNumberAllocationResult;
use App\Application\Sales\SalesNumberAllocator;
use App\Application\Sales\SalesNumberSequenceProvisioner;
use App\Application\Sales\SalesNumberType;
use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use Illuminate\Support\Facades\DB;

final readonly class DatabaseSalesNumberSequence implements SalesNumberAllocator, SalesNumberSequenceProvisioner
{
    public function __construct(private TransactionManager $transactions) {}

    public function ensureForAdministration(AdministrationId $administrationId): void
    {
        $now = now();
        DB::table('sales_number_sequences')->insertOrIgnore(array_map(
            static fn (SalesNumberType $type): array => [
                'administration_id' => $administrationId->toString(),
                'sequence_type' => $type->value,
                'next_value' => 1,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            SalesNumberType::cases(),
        ));
    }

    public function next(AdministrationId $administrationId, SalesNumberType $type): SalesNumberAllocationResult
    {
        return $this->transactions->run(function () use ($administrationId, $type): SalesNumberAllocationResult {
            $sequence = DB::table('sales_number_sequences')
                ->where('administration_id', $administrationId->toString())
                ->where('sequence_type', $type->value)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                return SalesNumberAllocationResult::sequenceMissing($type);
            }

            if (! (bool) $sequence->active) {
                return SalesNumberAllocationResult::sequenceInactive($type);
            }

            $value = (int) $sequence->next_value;
            DB::table('sales_number_sequences')
                ->where('administration_id', $administrationId->toString())
                ->where('sequence_type', $type->value)
                ->update(['next_value' => $value + 1, 'updated_at' => now()]);

            return SalesNumberAllocationResult::success($type, $type->number($value));
        });
    }
}
