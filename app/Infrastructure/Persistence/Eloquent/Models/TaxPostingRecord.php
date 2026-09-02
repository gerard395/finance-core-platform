<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class TaxPostingRecord extends Model
{
    protected $table = 'tax_postings';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'administration_id',
        'tax_code_id',
        'tax_treatment_definition_id',
        'tax_treatment_definition_version',
        'tax_treatment_group_id',
        'tax_leg_role',
        'treatment_type',
        'tax_jurisdiction',
        'reporting_classification',
        'deductibility_basis_points',
        'assessed_vat',
        'deductible_vat',
        'non_deductible_tax_cost',
        'supplier_vat_mode',
        'tax_rate',
        'taxable_base',
        'tax_amount',
        'currency',
        'direction',
        'type',
        'source_document_type',
        'source_document_id',
        'source_line_id',
        'posting_date',
        'journal_entry_id',
        'base_journal_entry_line_id',
        'tax_journal_entry_line_id',
        'reversed_tax_posting_id',
        'treatment',
        'vat_return_classification',
        'icp_classification',
    ];

    protected function casts(): array
    {
        return ['posting_date' => 'immutable_date'];
    }
}
