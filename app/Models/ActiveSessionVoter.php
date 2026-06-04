<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActiveSessionVoter extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ballot_id',
        'code',
        'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];
}
