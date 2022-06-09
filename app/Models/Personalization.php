<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Personalization
 *
 * @Bind("personalization")
 */
class Personalization extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'personalizations';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'owner',
        'photo_url'
    ];

    //

    public function scopeOwner($query, $id)
    {
        return $query->where('owner', $id);
    }
}
