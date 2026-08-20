<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class RoleRecord extends Model
{
    protected $table = 'roles';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'code', 'name', 'description', 'status'];
}
