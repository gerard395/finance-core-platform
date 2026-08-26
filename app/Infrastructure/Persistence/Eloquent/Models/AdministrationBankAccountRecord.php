<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class AdministrationBankAccountRecord extends Model
{
    protected $table = 'administration_bank_accounts';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'administration_id', 'iban', 'bic', 'account_holder', 'label', 'currency', 'status'];
}
