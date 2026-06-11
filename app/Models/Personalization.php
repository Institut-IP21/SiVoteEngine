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

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @param  mixed  $id
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeOwner(\Illuminate\Database\Eloquent\Builder $query, mixed $id): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('owner', $id);
    }
}
