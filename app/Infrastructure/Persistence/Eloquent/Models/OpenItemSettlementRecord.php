<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class OpenItemSettlementRecord extends Model
{
    protected $table = 'open_item_settlements';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'administration_id',
        'open_item_id',
        'payment_allocation_id',
        'effective_date',
        'amount',
        'currency',
        'source_journal_entry_id',
        'type',
        'reversed_settlement_id',
    ];

    protected function casts(): array
    {
        return ['effective_date' => 'immutable_date'];
    }
}
