<?php

namespace App\Models;

use App\Traits\Encryptable;
use App\Models\Concerns\HasUuidV4;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $ballot_id
 * @property array|null $values
 * @property string|null $cast_by
 * @property-read \App\Models\Ballot $ballot
 */
class Vote extends Model
{
    use Encryptable;
    use HasFactory;
    use HasUuidV4;

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

    public function ballot(): BelongsTo
    {
        return $this->belongsTo(Ballot::class);
    }
}
