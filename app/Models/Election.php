<?php

namespace App\Models;

use Database\Factories\ElectionFactory;
use GoldSpecDigital\LaravelEloquentUUID\Database\Eloquent\Uuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Dyrynda\Database\Support\CascadeSoftDeletes;

class Election extends Model
{
    use HasFactory;
    use SoftDeletes, CascadeSoftDeletes;
    use Uuid;

    const MODE_BASIC = 'basic';
    const MODE_SESSION = 'session';
    const MODES = [self::MODE_BASIC, self::MODE_SESSION];

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
        'mode'
    ];

    protected $casts = [
        'abstainable' => 'boolean',
    ];

    public function setModeAttribute($value)
    {
        if (!is_null($this->mode)) {
            throw new \Exception('Cannot change mode of an election');
        }

        $this->mode = $value;
    }

    public function ballots()
    {
        return $this->hasMany(Ballot::class);
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

    protected static function newFactory()
    {
        return ElectionFactory::new();
    }
}
