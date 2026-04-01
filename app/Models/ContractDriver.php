<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ContractDriver extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'contract_id',
        'client_id',
        'role',
        'sort_order',
        'full_name',
        'full_name_ar',
        'phone',
        'nationality',
        'date_of_birth',
        'identity_number',
        'residency_number',
        'license_number',
        'identity_expiry_date',
        'license_expiry_date',
        'extraction_status',
        'extracted_data',
        'raw_output',
        'confidence',
        'ai_reviewed',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'identity_expiry_date' => 'date',
            'license_expiry_date' => 'date',
            'extracted_data' => 'array',
            'raw_output' => 'array',
            'confidence' => 'decimal:4',
            'ai_reviewed' => 'boolean',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ContractDriverDocument::class);
    }

    public function frontDocument(): HasOne
    {
        return $this->hasOne(ContractDriverDocument::class)
            ->whereIn('side', ['front', 'single'])
            ->latestOfMany();
    }

    public function backDocument(): HasOne
    {
        return $this->hasOne(ContractDriverDocument::class)
            ->where('side', 'back')
            ->latestOfMany();
    }

    public function scopePrimary($query)
    {
        return $query->where('role', 'primary');
    }

    public function scopeAdditional($query)
    {
        return $query->where('role', 'additional');
    }
}
