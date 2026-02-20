<?php

namespace App\Models;

use App\Traits\Encryptable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vote extends Model
{
    use Encryptable;
    use HasFactory;
    use HasUuids;

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
