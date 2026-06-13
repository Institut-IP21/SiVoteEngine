<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property-read Personalization|null $personalization
 * @method static Builder<static>|ApiUser newModelQuery()
 * @method static Builder<static>|ApiUser newQuery()
 * @method static Builder<static>|ApiUser query()
 * @mixin \Eloquent
 */
class ApiUser extends Authenticatable
{

    public string $owner;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'owner',
    ];

    /** @return HasOne<Personalization, $this> */
    public function personalization(): HasOne
    {
        return $this->hasOne(Personalization::class, 'owner', 'owner');
    }
}
