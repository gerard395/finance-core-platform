<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class AdministrationMembershipRecord extends Model
{
    protected $table = 'administration_memberships';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'administration_id',
        'active',
        'valid_from',
        'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'valid_from' => 'immutable_datetime',
            'valid_until' => 'immutable_datetime',
        ];
    }
}
