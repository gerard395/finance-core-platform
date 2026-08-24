<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class SalesInvoiceLineRecord extends Model
{
    protected $table = 'sales_invoice_lines';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'administration_id', 'sales_invoice_id', 'description', 'quantity', 'unit_price_amount', 'currency', 'tax_code_id_snapshot', 'tax_code_snapshot', 'tax_name_snapshot', 'tax_rate_snapshot', 'tax_direction_snapshot', 'tax_treatment_snapshot', 'vat_return_classification_snapshot', 'icp_classification_snapshot'];
}
