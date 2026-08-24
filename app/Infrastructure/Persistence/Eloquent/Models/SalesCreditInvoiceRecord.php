<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class SalesCreditInvoiceRecord extends Model
{
    protected $table = 'sales_credit_invoices';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];
}
