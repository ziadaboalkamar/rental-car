<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
}

