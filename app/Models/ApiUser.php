<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class ApiUser extends Authenticatable
{

    public string $owner;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'owner',
    ];

    /** @return \Illuminate\Database\Eloquent\Relations\HasOne<Personalization, $this> */
    public function personalization(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Personalization::class, 'owner', 'owner');
    }
}
