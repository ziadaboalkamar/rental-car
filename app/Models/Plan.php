<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'features',
        'monthly_price',
        'monthly_price_id',
        'yearly_price',
        'yearly_price_id',
        'one_time_price',
        'one_time_price_id',
        'is_active',
    ];

    protected $casts = [
        'features' => 'array',
        'is_active' => 'boolean',
        'monthly_price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
        'one_time_price' => 'decimal:2',
    ];
}
