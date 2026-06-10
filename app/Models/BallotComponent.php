<?php

namespace App\Models;

use App\Models\Concerns\HasUuidV4;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $ballot_id
 * @property string $title
 * @property string $description
 * @property string $type
 * @property int $order
 * @property string $version
 * @property array $options
 * @property bool $active
 * @property bool $finished
 * @property-read \App\Models\Ballot $ballot
 * @property-read string $slug
 * @property-read string $component_path
 * @property-read string $form_template
 * @property-read string $form_template_livewire
 * @property-read string $result_template
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

    public function ballot(): BelongsTo
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
