<?php

namespace App\Models;

use App\Models\Concerns\HasUuidV4;
use App\Traits\Encryptable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vote extends Model
{
    use Encryptable;
    use HasFactory;
    use HasUuidV4;

    // Random v4 UUID primary key (ballot secrecy): never ordered. See HasUuidV4.
    protected $keyType = 'string';

    public $incrementing = false;

    protected $encryptable = [
        'values',
        'cast_by'
    ];

    public $fillable = [
        'values',
        'ballot_id',
        'cast_by'
    ];

    protected $casts = [
        'values' => 'array'
    ];

    public function ballot()
    {
        return $this->belongsTo(Ballot::class);
    }
}
