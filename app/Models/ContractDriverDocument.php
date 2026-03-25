<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ContractDriverDocument extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'contract_driver_id',
        'document_type',
        'side',
        'file_path',
        'file_name',
        'mime_type',
        'ocr_status',
        'ocr_provider',
        'raw_ocr_json',
        'normalized_json',
        'confidence',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_ocr_json' => 'array',
            'normalized_json' => 'array',
            'confidence' => 'decimal:4',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (ContractDriverDocument $document) {
            $path = ltrim((string) preg_replace('/^storage\//', '', (string) $document->file_path), '/');
            if ($path !== '' && Storage::disk(config('vilt-filepond.storage_disk'))->exists($path)) {
                Storage::disk(config('vilt-filepond.storage_disk'))->delete($path);
            }
        });
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(ContractDriver::class, 'contract_driver_id');
    }

    public function scopeLicenses($query)
    {
        return $query->where('document_type', 'driver_license');
    }

    public function scopeIdentity($query)
    {
        return $query->whereIn('document_type', ['id_card', 'residency_card']);
    }
}
