<?php

namespace App\Models;

use Database\Factories\ElectionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Dyrynda\Database\Support\CascadeSoftDeletes;

/**
 * @method static ElectionFactory factory(mixed? $parameters)
 */
class Election extends Model
{
    use HasFactory;
    use SoftDeletes, CascadeSoftDeletes;
    use HasUuids;

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

    public function ballots()
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
