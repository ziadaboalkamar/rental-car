<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use MohamedGaldi\ViltFilepond\Traits\HasFiles;

class Contract extends Model
{
    use BelongsToTenant;
    use HasFiles;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'reservation_id',
        'contract_number',
        'status',
        'contract_date',
        'renter_name',
        'renter_id_number',
        'renter_phone',
        'car_details',
        'plate_number',
        'start_date',
        'end_date',
        'total_amount',
        'currency',
        'notes',
        'ai_extraction_status',
        'ai_extracted_data',
    ];

    protected $casts = [
        'contract_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'total_amount' => 'decimal:2',
        'ai_extracted_data' => 'array',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function drivers(): HasMany
    {
        return $this->hasMany(ContractDriver::class);
    }

    public function primaryDriver(): HasOne
    {
        return $this->hasOne(ContractDriver::class)->where('role', 'primary');
    }

    public function additionalDrivers(): HasMany
    {
        return $this->hasMany(ContractDriver::class)->where('role', 'additional');
    }

    public function archiveFiles(): HasMany
    {
        return $this->hasMany(ContractArchiveFile::class);
    }

    public function damageReports(): HasMany
    {
        return $this->hasMany(CarDamageReport::class);
    }

    public function openedDamageCases(): HasMany
    {
        return $this->hasMany(CarDamageCase::class, 'opened_in_contract_id');
    }
}
