<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class SalesPostingConfigurationRecord extends Model
{
    protected $table = 'sales_posting_configurations';

    protected $primaryKey = 'administration_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['administration_id', 'sales_journal_id', 'accounts_receivable_ledger_account_id', 'revenue_ledger_account_id', 'output_vat_ledger_account_id'];
}
