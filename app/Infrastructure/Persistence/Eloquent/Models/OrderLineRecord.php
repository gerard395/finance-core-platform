<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class OrderLineRecord extends Model
{
    protected $table = 'order_lines';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'administration_id', 'order_id', 'description', 'quantity', 'unit_price_amount', 'currency'];
}
