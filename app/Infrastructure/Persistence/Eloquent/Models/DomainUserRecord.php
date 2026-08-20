<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class DomainUserRecord extends Model
{
    protected $table = 'domain_users';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'display_name',
        'email',
        'status',
    ];
}
