<?php

namespace App\Models;

use App\Traits\Encryptable;
use GoldSpecDigital\LaravelEloquentUUID\Database\Eloquent\Uuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vote extends Model
{
    use Encryptable;
    use HasFactory;
    use Uuid;

    protected $encryptable = [
        'values',
        'cast_by'
    ];

    protected $keyType = 'string';
    public $incrementing = false;

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
