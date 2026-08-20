<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class AdministrationMembershipRoleRecord extends Model
{
    protected $table = 'administration_membership_roles';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'membership_id', 'role_id', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
