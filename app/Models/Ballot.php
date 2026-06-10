<?php

namespace App\Models;

use App\Models\Concerns\HasUuidV4;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Dyrynda\Database\Support\CascadeSoftDeletes;

// Preview ballot can open engine URL <- /preview route

/**
 * @property string $id
 * @property string $election_id
 * @property string $title
 * @property bool $active
 * @property bool $finished
 * @property string $description
 * @property string $email_subject
 * @property string $email_template
 * @property bool $is_secret
 * @property string $mode
 * @property int|null $quorum
 * @property-read \App\Models\Election $election
 * @property-read \App\Models\BallotComponent[] $components
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Vote> $votes
 * @property-read \App\Models\Vote[] $cast_votes
 * @property-read int $votes_count
 * @property-read bool $locked
 */
class Ballot extends Model
{
    use HasFactory;
    use SoftDeletes, CascadeSoftDeletes;
    use HasUuidV4;

    const MODE_BASIC = 'basic';
    const MODE_SESSION = 'session';
    const MODES = [self::MODE_BASIC, self::MODE_SESSION];

    protected $keyType = 'string';
    public $incrementing = false;

    protected $cascadeDeletes = ['components', 'votes'];

    public $attributes = [
        'active' => false,
        'finished' => false,
        'description' => '',
        'email_subject' => '',
        'email_template' => '',
        'is_secret' => true,
        'mode' => self::MODE_BASIC,
        'quorum' => null,
    ];

    public $fillable = [
        'election_id',
        'title',
        'active',
        'description',
        'email_subject',
        'email_template',
        'is_secret',
        'mode',
        'quorum',
    ];

    protected $casts = [
        'active' => 'boolean',
        'is_secret' => 'boolean',
        'quorum' => 'integer',
    ];

    public function components(): HasMany
    {
        return $this->hasMany(BallotComponent::class)->orderBy('order');
    }

    public function getComponentsAttribute()
    {
        return $this->components()->get()->all();
    }

    public function disableComponents()
    {
        $this->components()->where('active', true)->update(['active' => false]);
    }

    public function getLockedAttribute()
    {
        return $this->active || $this->finished;
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function castVotes()
    {
        return $this->votes()->where('values', '!=', null)->get();
    }

    public function getCastVotesAttribute()
    {
        return $this->castVotes()->all();
    }

    public function getVotesCountAttribute()
    {
        return $this->castVotes()->count();
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function activate()
    {
        $this->active = true;
        return $this->save();
    }

    public function deactivate()
    {
        $this->active = false;
        $this->finished = true;
        return $this->save();
    }
}
