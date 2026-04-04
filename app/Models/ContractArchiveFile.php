<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ContractArchiveFile extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'contract_id',
        'contract_driver_id',
        'document_type',
        'title',
        'notes',
        'file_path',
        'file_name',
        'mime_type',
    ];

    protected static function booted(): void
    {
        static::deleting(function (ContractArchiveFile $file): void {
            $path = ltrim((string) preg_replace('/^storage\//', '', (string) $file->file_path), '/');
            if ($path !== '' && Storage::disk(config('vilt-filepond.storage_disk'))->exists($path)) {
                Storage::disk(config('vilt-filepond.storage_disk'))->delete($path);
            }
        });
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(ContractDriver::class, 'contract_driver_id');
    }
}
