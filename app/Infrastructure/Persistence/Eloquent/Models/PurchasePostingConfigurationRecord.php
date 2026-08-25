<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class PurchasePostingConfigurationRecord extends Model
{
    protected $table = 'purchase_posting_configurations';

    protected $primaryKey = 'administration_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];
}
