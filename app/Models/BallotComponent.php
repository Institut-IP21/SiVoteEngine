<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use App\BallotComponents\Support\ComponentRegistry;
use Database\Factories\BallotComponentFactory;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Concerns\HasUuidV4;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property-read string $component_path
 * @property-read string|null $type_name
 * @property-read string $form_template
 * @property-read string $form_template_livewire
 * @property-read string $result_template
 * @property-read string $slug
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
    /** @use HasFactory<BallotComponentFactory> */
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

    /**
     * The localized human name of this component's type, sourced from the component's
     * own getStrings()['name'] via the registry — the single source of truth (no
     * parallel hardcoded map in views). Null if the type/version isn't registered.
     */
    public function getTypeNameAttribute(): ?string
    {
        $registry = app(ComponentRegistry::class);
        if (! $registry->has($this->type, $this->version)) {
            return null;
        }

        return $registry->resolve($this->type, $this->version)->getMetadata()->strings['name'] ?? null;
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
        return array_filter(array_map(fn($option) => trim((string) $option), explode(',', $options)));
    }
}
