<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class JournalEntryLineRecord extends Model
{
    protected $table = 'journal_entry_lines';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'administration_id',
        'journal_entry_id',
        'ledger_account_id',
        'debit_amount',
        'credit_amount',
        'currency',
        'description',
    ];
}
