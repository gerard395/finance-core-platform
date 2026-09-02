<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class TaxTreatmentDefinitionRecord extends Model
{
    protected $table = 'tax_treatment_definitions';

    protected $primaryKey = null;

    public $incrementing = false;

    protected $fillable = [
        'id', 'administration_id', 'tax_code_id', 'version', 'treatment_type', 'jurisdiction',
        'vat_rate', 'supplier_vat_mode', 'deductibility_policy', 'leg_definitions', 'active', 'effective_from',
    ];

    protected function casts(): array
    {
        return ['leg_definitions' => 'array', 'active' => 'boolean', 'effective_from' => 'immutable_datetime'];
    }
}
