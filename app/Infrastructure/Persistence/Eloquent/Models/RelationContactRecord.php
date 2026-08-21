<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class RelationContactRecord extends Model
{
    protected $table = 'relation_contacts';

    protected $primaryKey = 'contact_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['contact_id', 'administration_id', 'relation_id', 'contact_name', 'email', 'phone', 'status'];
}
