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
 * @property array<string, mixed> $options
 * @property array<string, mixed>|null $settings
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
    /** @use HasFactory<\Database\Factories\BallotComponentFactory> */
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

    /** @return BelongsTo<Ballot, $this> */
    public function ballot(): BelongsTo
    {
        return $this->belongsTo(Ballot::class);
    }

    public function getSlugAttribute(): string
    {
        return Str::slug($this->title);
    }

    public function getComponentPathAttribute(): string
    {
        return $this->type . '/' . $this->version;
    }

    public function getFormTemplateAttribute(): string
    {
        return $this->type . '/' . $this->version . '/form';
    }

    public function getFormTemplateLivewireAttribute(): string
    {
        return $this->type . '/' . $this->version . '/form_livewire';
    }

    public function getResultTemplateAttribute(): string
    {
        return $this->type . '/' . $this->version . '/result';
    }

    /** @return array<int, string> */
    public static function parseOptionsString(string $options): array
    {
        return array_filter(array_map(function ($option) {
            return trim($option);
        }, explode(',', $options)));
    }
}
