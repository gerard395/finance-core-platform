<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class SalesInvoiceRecord extends Model
{
    protected $table = 'sales_invoices';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'administration_id', 'sales_invoice_number', 'customer_id', 'customer_relation_id_snapshot', 'customer_number_snapshot', 'customer_name_snapshot', 'invoice_address_id_snapshot', 'invoice_address_type_snapshot', 'invoice_address_line_1_snapshot', 'invoice_address_line_2_snapshot', 'invoice_postal_code_snapshot', 'invoice_city_snapshot', 'invoice_country_code_snapshot', 'source_order_id', 'currency', 'invoice_date', 'due_date', 'status'];
}
