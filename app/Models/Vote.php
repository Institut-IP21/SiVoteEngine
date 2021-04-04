<?php

namespace App\Models;

use GoldSpecDigital\LaravelEloquentUUID\Database\Eloquent\Uuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vote extends Model
{
    use HasFactory;
    use Uuid;

    protected $keyType = 'string';
    public $incrementing = false;

    public $fillable = [
        'values',
        'ballot_id'
    ];

    protected $casts = [
        'values' => 'array'
    ];

    public function ballot()
    {
        return $this->belongsTo(Ballot::class);
    }
}
