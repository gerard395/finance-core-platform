<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class BankingPostingConfigurationRecord extends Model
{
    protected $table = 'banking_posting_configurations';

    public $incrementing = false;

    protected $primaryKey = null;

    protected $fillable = ['administration_id', 'administration_bank_account_id', 'bank_journal_id', 'bank_ledger_account_id'];
}
