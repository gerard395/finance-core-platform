<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class LedgerAccountRecord extends Model
{
    protected $table = 'ledger_accounts';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'administration_id', 'code', 'name', 'type', 'status'];
}
