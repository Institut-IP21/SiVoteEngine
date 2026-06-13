<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static Builder<static>|ActiveSessionVoter newModelQuery()
 * @method static Builder<static>|ActiveSessionVoter newQuery()
 * @method static Builder<static>|ActiveSessionVoter query()
 * @mixin \Eloquent
 */
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
