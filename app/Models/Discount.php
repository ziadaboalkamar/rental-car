<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    protected $fillable = [
        'plan_id',
        'name',
        'code',
        'type',
        'value',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'value' => 'decimal:2',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
