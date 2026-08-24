<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class SalesInvoicePostingRecord extends Model
{
    protected $table = 'sales_invoice_postings';

    protected $primaryKey = null;

    public $incrementing = false;

    public const UPDATED_AT = null;

    protected $fillable = ['administration_id', 'sales_invoice_id', 'journal_entry_id', 'open_item_id', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }
}
