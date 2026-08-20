<?php

declare(strict_types=1);

namespace App\Infrastructure\Relations;

use App\Application\Relations\RelationNumberAllocationResult;
use App\Application\Relations\RelationNumberAllocator;
use App\Application\Relations\RelationNumberSequenceProvisioner;
use App\Application\Relations\RelationNumberType;
use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\CustomerNumber;
use App\Domain\Relations\ValueObjects\SupplierNumber;
use Illuminate\Support\Facades\DB;

final readonly class DatabaseRelationNumberSequence implements RelationNumberAllocator, RelationNumberSequenceProvisioner
{
    public function __construct(private TransactionManager $transactions) {}

    public function ensureForAdministration(AdministrationId $administrationId): void
    {
        $now = now();
        DB::table('relation_number_sequences')->insertOrIgnore(array_map(
            static fn (RelationNumberType $type): array => [
                'administration_id' => $administrationId->toString(),
                'sequence_type' => $type->value,
                'next_value' => 1,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            RelationNumberType::cases(),
        ));
    }

    public function next(AdministrationId $administrationId, RelationNumberType $type): RelationNumberAllocationResult
    {
        return $this->transactions->run(function () use ($administrationId, $type): RelationNumberAllocationResult {
            $sequence = DB::table('relation_number_sequences')
                ->where('administration_id', $administrationId->toString())
                ->where('sequence_type', $type->value)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                return RelationNumberAllocationResult::sequenceMissing();
            }

            if (! (bool) $sequence->active) {
                return RelationNumberAllocationResult::sequenceInactive();
            }

            $value = (int) $sequence->next_value;
            DB::table('relation_number_sequences')
                ->where('administration_id', $administrationId->toString())
                ->where('sequence_type', $type->value)
                ->update(['next_value' => $value + 1, 'updated_at' => now()]);

            $formatted = $type->format($value);

            return RelationNumberAllocationResult::success(match ($type) {
                RelationNumberType::Customer => new CustomerNumber($formatted),
                RelationNumberType::Supplier => new SupplierNumber($formatted),
            });
        });
    }
}
