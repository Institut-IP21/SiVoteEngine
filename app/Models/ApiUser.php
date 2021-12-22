<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class ApiUser extends Authenticatable
{

    public string $owner;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'owner',
    ];

    public function personalization()
    {
        return $this->hasOne(Personalization::class, 'owner', 'owner');
    }
}
