<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class AdministrationRecord extends Model
{
    protected $table = 'administrations';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'code',
        'name',
        'description',
        'base_currency',
        'status',
        'organisation_id',
        'organisation_display_name',
        'organisation_legal_name',
        'organisation_legal_form',
        'organisation_chamber_of_commerce_number',
        'organisation_vat_number',
        'fiscal_jurisdiction',
        'organisation_primary_address',
        'organisation_iban',
        'organisation_bic',
    ];
}
