<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class QuotationRecord extends Model
{
    protected $table = 'quotations';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'administration_id', 'quotation_number', 'customer_id', 'customer_relation_id_snapshot', 'customer_number_snapshot', 'customer_name_snapshot', 'currency', 'quotation_date', 'expiry_date', 'status'];
}
