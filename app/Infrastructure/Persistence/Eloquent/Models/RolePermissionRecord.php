<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class RolePermissionRecord extends Model
{
    protected $table = 'role_permissions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'role_id', 'permission_id', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
