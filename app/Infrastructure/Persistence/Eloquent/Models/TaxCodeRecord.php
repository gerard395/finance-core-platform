<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class TaxCodeRecord extends Model
{
    protected $table = 'tax_codes';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'administration_id', 'code', 'name', 'rate', 'direction', 'status', 'treatment', 'vat_return_classification', 'icp_classification'];
}
