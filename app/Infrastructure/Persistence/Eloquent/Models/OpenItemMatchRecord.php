<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class OpenItemMatchRecord extends Model
{
    protected $table = 'open_item_matches';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'administration_id',
        'debit_open_item_id',
        'credit_open_item_id',
        'amount',
        'currency',
        'occurred_on',
        'source_journal_entry_id',
    ];

    protected function casts(): array
    {
        return ['occurred_on' => 'immutable_date'];
    }
}
