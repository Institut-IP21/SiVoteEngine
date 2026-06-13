<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Concerns\HasUuidV4;
use Database\Factories\ElectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Dyrynda\Database\Support\CascadeSoftDeletes;

/**
 * @method static ElectionFactory factory(mixed? $parameters)
 * @property string $id
 * @property string $title
 * @property string|null $description
 * @property int $level
 * @property string $owner
 * @property bool $abstainable
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Ballot> $ballots
 * @property-read int|null $ballots_count
 * @property-read mixed $active
 * @property-read mixed $locked
 * @method static Builder<static>|Election newModelQuery()
 * @method static Builder<static>|Election newQuery()
 * @method static Builder<static>|Election onlyTrashed()
 * @method static Builder<static>|Election query()
 * @method static Builder<static>|Election whereAbstainable($value)
 * @method static Builder<static>|Election whereCreatedAt($value)
 * @method static Builder<static>|Election whereDeletedAt($value)
 * @method static Builder<static>|Election whereDescription($value)
 * @method static Builder<static>|Election whereId($value)
 * @method static Builder<static>|Election whereLevel($value)
 * @method static Builder<static>|Election whereOwner($value)
 * @method static Builder<static>|Election whereTitle($value)
 * @method static Builder<static>|Election whereUpdatedAt($value)
 * @method static Builder<static>|Election withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Election withoutTrashed()
 * @mixin \Eloquent
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
        'abstainable',
        'locale'
    ];

    protected $casts = [
        'abstainable' => 'boolean',
    ];

    public function ballots()
    {
        return $this->hasMany(Ballot::class)->orderBy('created_at', 'desc');
    }

    public function getActiveAttribute()
    {
        return $this->ballots()->get()->contains(fn(Ballot $ballot) => $ballot->active);
    }

    public function getLockedAttribute()
    {
        return $this->ballots()->get()->contains(fn(Ballot $ballot) => $ballot->locked);
    }
}
