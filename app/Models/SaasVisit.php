<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaasVisit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'landing_path',
        'referrer_url',
        'referrer_host',
        'referrer_path',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'ip_address',
        'user_agent',
        'visited_at',
    ];

    protected function casts(): array
    {
        return [
            'visited_at' => 'datetime',
        ];
    }
}
