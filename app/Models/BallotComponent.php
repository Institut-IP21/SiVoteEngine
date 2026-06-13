<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Database\Factories\BallotComponentFactory;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Concerns\HasUuidV4;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property array<string, mixed>|null $settings Per-component settings payload (e.g. YesNo's pass_threshold).
 * @property string $id
 * @property string $ballot_id
 * @property string $title
 * @property string|null $description
 * @property string $type
 * @property array<array-key, mixed> $options
 * @property string|null $deleted_at
 * @property string $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $order
 * @property bool $active
 * @property bool $finished
 * @property-read Ballot|null $ballot
 * @property-read mixed $component_path
 * @property-read mixed $form_template
 * @property-read mixed $form_template_livewire
 * @property-read mixed $result_template
 * @property-read mixed $slug
 * @method static BallotComponentFactory factory($count = null, $state = [])
 * @method static Builder<static>|BallotComponent newModelQuery()
 * @method static Builder<static>|BallotComponent newQuery()
 * @method static Builder<static>|BallotComponent query()
 * @method static Builder<static>|BallotComponent whereActive($value)
 * @method static Builder<static>|BallotComponent whereBallotId($value)
 * @method static Builder<static>|BallotComponent whereCreatedAt($value)
 * @method static Builder<static>|BallotComponent whereDeletedAt($value)
 * @method static Builder<static>|BallotComponent whereDescription($value)
 * @method static Builder<static>|BallotComponent whereFinished($value)
 * @method static Builder<static>|BallotComponent whereId($value)
 * @method static Builder<static>|BallotComponent whereOptions($value)
 * @method static Builder<static>|BallotComponent whereOrder($value)
 * @method static Builder<static>|BallotComponent whereSettings($value)
 * @method static Builder<static>|BallotComponent whereTitle($value)
 * @method static Builder<static>|BallotComponent whereType($value)
 * @method static Builder<static>|BallotComponent whereUpdatedAt($value)
 * @method static Builder<static>|BallotComponent whereVersion($value)
 * @mixin \Eloquent
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

    public static function parseOptionsString($options): array
    {
        return array_filter(array_map(fn($option) => trim((string) $option), explode(',', $options)));
    }
}
