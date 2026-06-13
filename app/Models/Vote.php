<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Database\Factories\VoteFactory;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Concerns\HasUuidV4;
use App\Traits\Encryptable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $ballot_id
 * @property array<array-key, mixed>|null $values
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $cast_by
 * @property-read Ballot|null $ballot
 * @method static VoteFactory factory($count = null, $state = [])
 * @method static Builder<static>|Vote newModelQuery()
 * @method static Builder<static>|Vote newQuery()
 * @method static Builder<static>|Vote query()
 * @method static Builder<static>|Vote whereBallotId($value)
 * @method static Builder<static>|Vote whereCastBy($value)
 * @method static Builder<static>|Vote whereCreatedAt($value)
 * @method static Builder<static>|Vote whereId($value)
 * @method static Builder<static>|Vote whereUpdatedAt($value)
 * @method static Builder<static>|Vote whereValues($value)
 * @mixin \Eloquent
 */
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
