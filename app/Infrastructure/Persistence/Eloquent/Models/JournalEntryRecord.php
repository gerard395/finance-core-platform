<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class JournalEntryRecord extends Model
{
    protected $table = 'journal_entries';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'administration_id', 'journal_id', 'posting_date', 'reference', 'status'];

    protected function casts(): array
    {
        return ['posting_date' => 'immutable_date'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLineRecord::class, 'journal_entry_id')->orderBy('id');
    }
}
