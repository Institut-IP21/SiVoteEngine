<?php

namespace App\Models;

use App\Models\Concerns\HasUuidV4;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property array<string, mixed>|null $settings Per-component settings payload (e.g. YesNo's pass_threshold).
 */
class BallotComponent extends Model
{
    use HasFactory;
    use HasUuidV4;

    protected $keyType = 'string';

    public $incrementing = false;

    public $fillable = [
        'title',
        'description',
        'type',
        'order',
        'version',
        'options',
        'settings',
        'ballot_id',
        'active',
        'finished',
    ];

    protected $casts = [
        'options' => 'array',
        'settings' => 'array',
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
