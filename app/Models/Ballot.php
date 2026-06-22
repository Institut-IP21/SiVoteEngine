<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Database\Factories\BallotFactory;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Concerns\HasUuidV4;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Dyrynda\Database\Support\CascadeSoftDeletes;

// Preview ballot can open engine URL <- /preview route
/**
 * @property-read bool $quorum_met D11: quorum===null OR votes_count >= quorum.
 * @property-read int $electorate_size Issued-code count (all Vote rows for this ballot).
 * @property-read int $votes_count Cast-vote turnout.
 * @property string $id
 * @property string $election_id
 * @property string $title
 * @property string|null $email_subject
 * @property string|null $email_template
 * @property string|null $result_email_subject
 * @property string|null $result_email_template
 * @property string|null $description
 * @property bool $active
 * @property bool $finished
 * @property Carbon|null $opened_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property bool $is_secret
 * @property string $mode
 * @property int|null $quorum
 * @property-read Collection<int, BallotComponent> $components
 * @property-read int|null $components_count
 * @property-read Election|null $election
 * @property-read array<int, Vote> $cast_votes
 * @property-read bool $locked
 * @property-read Collection<int, Vote> $votes
 * @method static BallotFactory factory($count = null, $state = [])
 * @method static Builder<static>|Ballot newModelQuery()
 * @method static Builder<static>|Ballot newQuery()
 * @method static Builder<static>|Ballot onlyTrashed()
 * @method static Builder<static>|Ballot query()
 * @method static Builder<static>|Ballot whereActive($value)
 * @method static Builder<static>|Ballot whereCreatedAt($value)
 * @method static Builder<static>|Ballot whereDeletedAt($value)
 * @method static Builder<static>|Ballot whereDescription($value)
 * @method static Builder<static>|Ballot whereElectionId($value)
 * @method static Builder<static>|Ballot whereEmailSubject($value)
 * @method static Builder<static>|Ballot whereEmailTemplate($value)
 * @method static Builder<static>|Ballot whereFinished($value)
 * @method static Builder<static>|Ballot whereId($value)
 * @method static Builder<static>|Ballot whereIsSecret($value)
 * @method static Builder<static>|Ballot whereMode($value)
 * @method static Builder<static>|Ballot whereOpenedAt($value)
 * @method static Builder<static>|Ballot whereClosedAt($value)
 * @method static Builder<static>|Ballot whereQuorum($value)
 * @method static Builder<static>|Ballot whereTitle($value)
 * @method static Builder<static>|Ballot whereUpdatedAt($value)
 * @method static Builder<static>|Ballot withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Ballot withoutTrashed()
 * @mixin \Eloquent
 */
class Ballot extends Model
{
    /** @use HasFactory<BallotFactory> */
    use HasFactory;
    use SoftDeletes, CascadeSoftDeletes;
    use HasUuidV4;

    protected $keyType = 'string';

    public $incrementing = false;

    const MODE_BASIC = 'basic';
    const MODE_SESSION = 'session';
    const MODES = [self::MODE_BASIC, self::MODE_SESSION];

    /** @var list<string> */
    protected $cascadeDeletes = ['components', 'votes'];

    public $attributes = [
        'active' => false,
        'finished' => false,
        'description' => '',
        'email_subject' => '',
        'email_template' => '',
        'result_email_subject' => '',
        'result_email_template' => '',
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
        'result_email_subject',
        'result_email_template',
        'is_secret',
        'mode',
        'quorum',
    ];

    protected $casts = [
        'active' => 'boolean',
        'is_secret' => 'boolean',
        'quorum' => 'integer',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    /** @return HasMany<BallotComponent, $this> */
    public function components(): HasMany
    {
        return $this->hasMany(BallotComponent::class)->orderBy('order');
    }

    /** @return array<int, BallotComponent> */
    public function getComponentsAttribute(): array
    {
        return $this->components()->get()->all();
    }

    public function disableComponents(): void
    {
        $this->components()->where('active', true)->update(['active' => false]);
    }

    public function getLockedAttribute(): bool
    {
        return $this->active || $this->finished;
    }

    /** @return HasMany<Vote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    /** @return Collection<int, Vote> */
    public function castVotes(): Collection
    {
        return $this->votes()->where('values', '!=', null)->get();
    }

    /** @return array<int, Vote> */
    public function getCastVotesAttribute(): array
    {
        return $this->castVotes()->all();
    }

    public function getVotesCountAttribute(): int
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

    /** @return BelongsTo<Election, $this> */
    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function activate(): bool
    {
        $this->active = true;
        // Stamp the first time it opens; a ballot can't reactivate per the
        // domain, but guard against clobbering an existing timestamp anyway.
        if ($this->opened_at === null) {
            $this->opened_at = now();
        }
        return $this->save();
    }

    public function deactivate(): bool
    {
        $this->active = false;
        $this->finished = true;
        $this->closed_at = now();
        return $this->save();
    }
}
