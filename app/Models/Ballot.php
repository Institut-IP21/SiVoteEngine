<?php

namespace App\Models;

use App\Models\Concerns\HasUuidV4;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Dyrynda\Database\Support\CascadeSoftDeletes;

// Preview ballot can open engine URL <- /preview route

/**
 * @property-read bool $quorum_met D11: quorum===null OR votes_count >= quorum.
 * @property-read int $electorate_size Issued-code count (all Vote rows for this ballot).
 * @property-read int $votes_count Cast-vote turnout.
 */
class Ballot extends Model
{
    use HasFactory;
    use SoftDeletes, CascadeSoftDeletes;
    use HasUuidV4;

    protected $keyType = 'string';

    public $incrementing = false;

    const MODE_BASIC = 'basic';
    const MODE_SESSION = 'session';
    const MODES = [self::MODE_BASIC, self::MODE_SESSION];

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

    public function components()
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

    public function votes()
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

    /**
     * D11: total electorate size = the number of issued voting codes, i.e. every
     * Vote row for this ballot regardless of whether it carries a cast value.
     */
    public function getElectorateSizeAttribute(): int
    {
        return $this->votes()->count();
    }

    /**
     * D11: quorum is met when no quorum is set (null) or turnout (cast votes)
     * reaches it. The result is only binding when this is true.
     */
    public function getQuorumMetAttribute(): bool
    {
        return $this->quorum === null || $this->votes_count >= $this->quorum;
    }

    public function election()
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
