<?php

namespace App\Models;

use GoldSpecDigital\LaravelEloquentUUID\Database\Eloquent\Uuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BallotComponent extends Model
{
    use HasFactory;
    use Uuid;

    protected $keyType = 'string';
    public $incrementing = false;

    public $fillable = [
        'title',
        'description',
        'type',
        'order',
        'version',
        'options',
        'ballot_id',
        'active',
        'finished',
    ];

    protected $casts = [
        'options' => 'array',
        'order' => 'integer',
        'active' => 'boolean',
        'finished' => 'boolean',
    ];

    protected $appends = ['slug'];

    public function ballot()
    {
        return $this->belongsTo(Ballot::class);
    }

    public function getSlugAttribute()
    {
        return Str::slug($this->title);
    }

    public function getComponentPathAttribute()
    {
        return $this->type . '/' . $this->version;
    }

    public function getFormTemplateAttribute()
    {
        return $this->type . '/' . $this->version . '/form';
    }

    public function getFormTemplateLivewireAttribute()
    {
        return $this->type . '/' . $this->version . '/form_livewire';
    }

    public function getResultTemplateAttribute()
    {
        return $this->type . '/' . $this->version . '/result';
    }

    public static function parseOptionsString($options)
    {
        return array_filter(array_map(function ($option) {
            return trim($option);
        }, explode(',', $options)));
    }
}
