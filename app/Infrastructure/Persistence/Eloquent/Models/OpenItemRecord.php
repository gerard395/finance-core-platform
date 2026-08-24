<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class OpenItemRecord extends Model
{
    protected $table = 'open_items';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'administration_id',
        'relation_id',
        'journal_entry_id',
        'open_item_type',
        'side',
        'original_amount',
        'currency',
        'opened_on',
    ];

    protected function casts(): array
    {
        return ['opened_on' => 'immutable_date'];
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(OpenItemSettlementRecord::class, 'open_item_id')
            ->orderBy('effective_date')
            ->orderBy('id');
    }
}
