<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'address',
        'phone',
        'email',
    ];

    /**
     * Get the users assigned to this branch.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
