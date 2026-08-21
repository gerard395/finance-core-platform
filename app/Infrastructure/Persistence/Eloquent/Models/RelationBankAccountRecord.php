<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class RelationBankAccountRecord extends Model
{
    protected $table = 'relation_bank_accounts';

    protected $primaryKey = 'bank_account_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['bank_account_id', 'administration_id', 'relation_id', 'iban', 'bic', 'account_name', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
