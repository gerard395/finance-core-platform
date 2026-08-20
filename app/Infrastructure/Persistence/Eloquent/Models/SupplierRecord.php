<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class SupplierRecord extends Model
{
    protected $table = 'suppliers';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'administration_id', 'relation_id', 'supplier_number', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
