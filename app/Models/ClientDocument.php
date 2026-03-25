<?php

namespace App\Models;

use App\Enums\ClientDocumentExtractionStatus;
use App\Enums\ClientDocumentType;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MohamedGaldi\ViltFilepond\Traits\HasFiles;

class ClientDocument extends Model
{
    use BelongsToTenant;
    use HasFiles;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'document_type',
        'extraction_status',
        'extraction_provider',
        'extraction_engine',
        'raw_text',
        'raw_output',
        'extracted_data',
        'approved_data',
        'confidence',
        'reviewed_at',
        'reviewed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => ClientDocumentType::class,
            'extraction_status' => ClientDocumentExtractionStatus::class,
            'raw_output' => 'array',
            'extracted_data' => 'array',
            'approved_data' => 'array',
            'confidence' => 'decimal:4',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
