<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class QuotationRecord extends Model
{
    protected $table = 'quotations';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'administration_id', 'quotation_number', 'customer_id', 'customer_relation_id_snapshot', 'customer_number_snapshot', 'customer_name_snapshot', 'quotation_address_id_snapshot', 'quotation_address_type_snapshot', 'quotation_address_line_1_snapshot', 'quotation_address_line_2_snapshot', 'quotation_postal_code_snapshot', 'quotation_city_snapshot', 'quotation_country_code_snapshot', 'currency', 'quotation_date', 'expiry_date', 'status'];
}
