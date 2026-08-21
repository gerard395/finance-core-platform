<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class OrderRecord extends Model
{
    protected $table = 'orders';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'administration_id', 'order_number', 'customer_id', 'customer_relation_id_snapshot', 'customer_number_snapshot', 'customer_name_snapshot', 'source_quotation_id', 'currency', 'order_date', 'status'];
}
