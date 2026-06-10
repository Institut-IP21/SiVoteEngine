<?php

namespace App\Models;

use Database\Factories\ElectionFactory;
use App\Models\Concerns\HasUuidV4;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Dyrynda\Database\Support\CascadeSoftDeletes;

/**
 * @property string $id
 * @property string $owner
 * @property string $title
 * @property string $description
 * @property int $level
 * @property bool $abstainable
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Ballot> $ballots
 * @property-read bool $active
 * @property-read bool $locked
 *
 * @method static ElectionFactory factory($count = null, $state = [])
 */
class Election extends Model
{
    use HasFactory;
    use SoftDeletes, CascadeSoftDeletes;
    use HasUuidV4;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $cascadeDeletes = ['ballots'];

    protected $attributes = [
        'abstainable' => false,
        'description' => '',
        'level' => 1,
    ];

    public $fillable = [
        'owner',
        'title',
        'description',
        'level',
        'abstainable'
    ];

    protected $casts = [
        'abstainable' => 'boolean',
    ];

    /** @return HasMany<Ballot, $this> */
    public function ballots(): HasMany
    {
        return $this->hasMany(Ballot::class)->orderBy('created_at', 'desc');
    }

    public function getActiveAttribute()
    {
        return $this->ballots()->get()->contains(function (Ballot $ballot) {
            return $ballot->active;
        });
    }

    public function getLockedAttribute()
    {
        return $this->ballots()->get()->contains(function (Ballot $ballot) {
            return $ballot->locked;
        });
    }
}
