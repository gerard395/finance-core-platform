<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class RelationRecord extends Model
{
    protected $table = 'relations';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'administration_id', 'code', 'display_name', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
