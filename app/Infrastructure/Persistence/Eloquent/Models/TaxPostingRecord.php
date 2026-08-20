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
    ];

    protected function casts(): array
    {
        return ['posting_date' => 'immutable_date'];
    }
}
