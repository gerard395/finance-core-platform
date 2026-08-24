<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class SalesCreditInvoiceLineRecord extends Model
{
    protected $table = 'sales_credit_invoice_lines';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];
}
