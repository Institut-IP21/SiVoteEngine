<?php

namespace App\Models;

use GoldSpecDigital\LaravelEloquentUUID\Database\Eloquent\Uuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Dyrynda\Database\Support\CascadeSoftDeletes;

// Preview ballot can open engine URL <- /preview route

class Ballot extends Model
{
    use HasFactory;
    use SoftDeletes, CascadeSoftDeletes;
    use Uuid;

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
    ];

    public $fillable = [
        'election_id',
        'title',
        'active',
        'description',
        'email_subject',
        'email_template',
        'is_secret',
        'mode'
    ];

    protected $casts = [
        'active' => 'boolean',
        'is_secret' => 'boolean'
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
