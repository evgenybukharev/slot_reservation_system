<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hold extends Model
{

    protected $casts = [
        'expired_at' => 'datetime',
    ];
    //
    protected $fillable = [
        'slot_id',
        'status',
        'idempotency_key',
        'expires_at',
    ];
}
