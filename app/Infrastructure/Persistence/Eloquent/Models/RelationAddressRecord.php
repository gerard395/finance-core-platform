<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class RelationAddressRecord extends Model
{
    protected $table = 'relation_addresses';

    protected $primaryKey = 'address_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['address_id', 'administration_id', 'relation_id', 'address_type', 'address_line_1', 'address_line_2', 'postal_code', 'city', 'country_code', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
