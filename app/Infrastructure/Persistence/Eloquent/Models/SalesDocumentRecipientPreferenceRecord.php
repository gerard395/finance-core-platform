<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class SalesDocumentRecipientPreferenceRecord extends Model
{
    protected $table = 'sales_document_recipient_preferences';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'administration_id', 'relation_id', 'purpose', 'contact_id'];
}
