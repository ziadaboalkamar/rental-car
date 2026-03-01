<?php

namespace App\Models;

use Laratrust\Models\Permission as PermissionModel;
use App\Traits\BelongsToTenant;

class Permission extends PermissionModel
{
    use BelongsToTenant;

    public $guarded = [];

    protected $fillable = ['name', 'display_name', 'description', 'tenant_id'];
}
