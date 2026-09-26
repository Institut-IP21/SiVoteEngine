<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use App\Http\Resources\Ballot as BallotResource;
use App\Http\Resources\BallotComponent as ComponentResource;
use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Services\BallotService;
use Illuminate\Http\Request;

class BallotComponentApiController extends Controller
{
    public function __construct(
        protected readonly BallotService $ballotService,
    ) {}

    /** @return array{data: array<mixed>} */
    public function list(Election $election): array
    {
        return [
            'data' => $this->ballotService->getComponentTree()
        ];
    }

    public function create(Election $election, Ballot $ballot, Request $request): JsonResponse|ComponentResource
    {
        $params = $request->all();
        $settings = [
            'title' => 'required|string|min:1',
            'description' => 'nullable|string|min:1',
            'order' => 'nullable|integer',
            'type' => [
                'required',
                'string',
                'bail',
                function (string $attribute, $value, $fail): void {
                    if (!in_array($value, $this->ballotService->getBallotTypes())) {
                        $fail($attribute . ' must be a valid ballot type.');
                    }
                }
            ],
            'version' => [
                'required',
                'string',
                'bail',
                function (string $attribute, $value, $fail) use ($params): void {
                    if (!in_array($value, $this->ballotService->getBallotVersions($params['type']))) {
                        $fail($attribute . ' must be a valid version.');
                    }
                }
            ],
            'settings' => 'nullable|array',
            'settings.pass_threshold' => $this->passThresholdRule(),
            'settings.seats' => 'sometimes|nullable|integer|min:1',
            'settings.categories' => 'sometimes|nullable|array',
            'settings.categories.*' => 'nullable|string',
            'settings.quota' => 'sometimes|nullable|array',
            'settings.quota.category' => 'required_with:settings.quota|string',
            'settings.quota.type' => 'required_with:settings.quota|in:min,max',
            'settings.quota.count' => 'required_with:settings.quota|integer|min:0',
            'settings.quota.binding' => 'sometimes|boolean',
        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        $componentInstance = $this->ballotService->resolveComponent($params['type'], $params['version']);
        $metadata = $componentInstance->getMetadata();
        $secondaryValidation = [];

        if ($metadata->needsOptions) {
            $secondaryValidation = array_merge($secondaryValidation, $metadata->optionsValidator);
        }

        if ($errors = $this->findErrors($params, $secondaryValidation)) {
            return $errors;
        }

        $options = $metadata->needsOptions ? $params['options'] : $metadata->presetOptions;

        $attributes = [
            'ballot_id' => $ballot->id,
            'description' => $params['description'] ?? '',
            'title' => $params['title'],
            'type' => $params['type'],
            'version' => $params['version'],
            'order' => $params['order'] ?? 0,
            'options' => $options,
        ];

        if (($settingsPayload = $this->buildSettings($params)) !== null) {
            $attributes['settings'] = $settingsPayload;
        }

        $component = BallotComponent::create($attributes);

        return new ComponentResource($component);
    }

    /**
     * Reorder this ballot's components to match a given id sequence: each
     * component's `order` becomes its index in `order[]`. Ids not on this
     * ballot are ignored (see BallotService::reorderComponents). Returns the
     * refreshed Ballot resource so the GUI can rebuild from authoritative data.
     */
    public function order(Election $election, Ballot $ballot, Request $request): ResponseFactory|Response|JsonResponse|BallotResource
    {
        if ($ballot->finished) {
            return response('Finished ballots can not be reordered', 403);
        }

        $params = $request->all();
        $settings = [
            'order'   => 'required|array',
            'order.*' => 'uuid',
        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        /** @var array<int, string> $orderedIds */
        $orderedIds = array_values($params['order']);

        $this->ballotService->reorderComponents($ballot, $orderedIds);

        return new BallotResource($ballot->refresh());
    }

    public function read(Election $election, Ballot $ballot, BallotComponent $component, Request $request): ComponentResource
    {
        return new ComponentResource($component);
    }

    public function update(Election $election, Ballot $ballot, BallotComponent $component, Request $request): JsonResponse|ComponentResource
    {
        $params = $request->all();
        $settings = [
            'title' => 'bail|required|string|min:1',
            'description' => 'nullable|string|min:1',
            'order' => 'nullable|integer',
            'type' => [
                'bail',
                'required',
                'string',
                function (string $attribute, $value, $fail): void {
                    if (!in_array($value, $this->ballotService->getBallotTypes())) {
                        $fail($attribute . ' must be a valid ballot type.');
                    }
                }
            ],
            'version' => [
                'bail',
                'required',
                'string',
                function (string $attribute, $value, $fail) use ($params): void {
                    if (!in_array($value, $this->ballotService->getBallotVersions($params['type']))) {
                        $fail($attribute . ' must be a valid version.');
                    }
                }
            ],
            'settings' => 'nullable|array',
            'settings.pass_threshold' => $this->passThresholdRule(),
            'settings.seats' => 'sometimes|nullable|integer|min:1',
            'settings.categories' => 'sometimes|nullable|array',
            'settings.categories.*' => 'nullable|string',
            'settings.quota' => 'sometimes|nullable|array',
            'settings.quota.category' => 'required_with:settings.quota|string',
            'settings.quota.type' => 'required_with:settings.quota|in:min,max',
            'settings.quota.count' => 'required_with:settings.quota|integer|min:0',
            'settings.quota.binding' => 'sometimes|boolean',
        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        $componentInstance = $this->ballotService->resolveComponent($params['type'], $params['version']);
        $metadata = $componentInstance->getMetadata();
        $secondaryValidation = [];

        if (array_key_exists('options', $params) && $metadata->needsOptions) {
            $secondaryValidation = array_merge($secondaryValidation, $metadata->optionsValidator);
        }

        if ($errors = $this->findErrors($params, $secondaryValidation)) {
            return $errors;
        }

        if (array_key_exists('options', $params) && $metadata->needsOptions) {
            $component->options = $params['options'];
        }

        if ($params['type']) {
            $component->type = $params['type'];
        }

        if (array_key_exists('order', $params)) {
            $component->order = $params['order'];
        }

        if ($params['version']) {
            $component->version = $params['version'];
        }

        if ($params['title']) {
            $component->title = $params['title'];
        }

        if (array_key_exists('description', $params)) {
            $component->description = $params['description'];
        }

        if (($settingsPayload = $this->buildSettings($params)) !== null) {
            $component->settings = $settingsPayload;
        }

        $component->save();

        return new ComponentResource($component);
    }

    /**
     * Validation rule for `settings.pass_threshold`: optional; valid only when it
     * is numeric in [50,100] or one of the supported presets. Mirrors the CLI's
     * accepted set so the API and BallotComponentCreate stay in lockstep.
     *
     * @return array<int, mixed>
     */
    private function passThresholdRule(): array
    {
        return [
            'nullable',
            function (string $attribute, $value, $fail): void {
                $presets = ['two_thirds', 'three_quarters'];
                if (is_string($value) && in_array($value, $presets, true)) {
                    return;
                }
                if (is_numeric($value) && $value >= 50 && $value <= 100) {
                    return;
                }
                $fail($attribute . ' must be a number between 50 and 100 or one of: ' . implode(', ', $presets) . '.');
            },
        ];
    }

    /**
     * Build the persisted `settings` payload from a request. A per-key
     * whitelist: each of `pass_threshold` (YesNo), `seats` (ApprovalVote /
     * OrderedList), and `categories` / `quota` (OrderedList) is independently
     * normalised and dropped — never persisted — when absent, empty, or
     * malformed, rather than failing the whole request; request-level
     * rejection of clearly-invalid shapes is handled by the validation rules
     * in create()/update(). This is a second line of defense so this method
     * never writes obvious garbage even if it's ever reached with
     * unvalidated input — the engine's own tally logic (`OrderedList::
     * parseQuota`/`parseCategories`) re-validates again at calculation time.
     *
     * Returns null only when NONE of the whitelisted keys resolve to a
     * value, preserving the original "omit settings entirely" semantics
     * (e.g. a YesNo with no threshold, or any type with no settings at all,
     * still stores `null`, not `[]`).
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    private function buildSettings(array $params): ?array
    {
        if (!isset($params['settings']) || !is_array($params['settings'])) {
            return null;
        }

        /** @var array<string, mixed> $raw */
        $raw = $params['settings'];
        $settings = [];

        if (($passThreshold = $this->buildPassThreshold($raw)) !== null) {
            $settings['pass_threshold'] = $passThreshold;
        }

        if (($seats = $this->buildSeats($raw)) !== null) {
            $settings['seats'] = $seats;
        }

        if (($categories = $this->buildCategories($raw)) !== null) {
            $settings['categories'] = $categories;
        }

        if (($quota = $this->buildQuota($raw)) !== null) {
            $settings['quota'] = $quota;
        }

        return $settings === [] ? null : $settings;
    }

    /**
     * `pass_threshold` (YesNo): unchanged behaviour from before this method
     * was generalized — numeric strings normalise to int/float so the value
     * round-trips as a number; preset strings pass through untouched.
     *
     * @param array<string, mixed> $settings
     */
    private function buildPassThreshold(array $settings): int|float|string|null
    {
        if (!array_key_exists('pass_threshold', $settings)) {
            return null;
        }

        $value = $settings['pass_threshold'];
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? $value + 0 : $value;
    }

    /**
     * `seats` (ApprovalVote / OrderedList): a positive integer. The HTTP
     * validation rule (`settings.seats`) already enforces this on a normal
     * request; this normalises numeric strings to `int` and drops anything
     * that isn't a valid positive integer rather than guessing.
     *
     * @param array<string, mixed> $settings
     */
    private function buildSeats(array $settings): ?int
    {
        if (!array_key_exists('seats', $settings)) {
            return null;
        }

        $value = $settings['seats'];
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        $seats = (int) $value;
        return $seats >= 1 ? $seats : null;
    }

    /**
     * `categories` (OrderedList): a candidate-label => category-string map.
     * Mirrors `OrderedList::parseCategories()`'s own predicate exactly (only
     * string values survive) so nothing is persisted here that the tally
     * would drop anyway.
     *
     * @param array<string, mixed> $settings
     * @return array<string, string>|null
     */
    private function buildCategories(array $settings): ?array
    {
        if (!array_key_exists('categories', $settings) || !is_array($settings['categories'])) {
            return null;
        }

        $categories = [];
        foreach ($settings['categories'] as $option => $category) {
            if (is_string($category)) {
                $categories[(string) $option] = $category;
            }
        }

        return $categories === [] ? null : $categories;
    }

    /**
     * `quota` (OrderedList): `{category, type, count, binding}`. The HTTP
     * validation rules already enforce the basic shape (string category,
     * type in [min,max], integer count >= 0, optional bool binding); this
     * normalises the numeric count to `int` and defaults `binding` to true.
     * Deliberately does NOT enforce the stricter "min needs count>=1"
     * business rule here — `OrderedList::parseQuota()` re-checks that at
     * tally time and drops-with-warning if it's violated, which is the
     * intended lenient behaviour (don't 500, don't hard-fail the request
     * over it, but don't guess either).
     *
     * @param array<string, mixed> $settings
     * @return array{category:string,type:string,count:int,binding:bool}|null
     */
    private function buildQuota(array $settings): ?array
    {
        if (!array_key_exists('quota', $settings) || !is_array($settings['quota'])) {
            return null;
        }

        $quota = $settings['quota'];
        $category = $quota['category'] ?? null;
        $type = $quota['type'] ?? null;
        $count = $quota['count'] ?? null;
        $binding = $quota['binding'] ?? null;

        if (
            !is_string($category)
            || $category === ''
            || !in_array($type, ['min', 'max'], true)
            || !is_numeric($count)
        ) {
            return null;
        }

        $count = (int) $count;
        if ($count < 0) {
            return null;
        }

        return [
            'category' => $category,
            'type' => $type,
            'count' => $count,
            'binding' => is_bool($binding) ? $binding : true,
        ];
    }

    public function delete(Election $election, Ballot $ballot, BallotComponent $component): bool|null
    {
        return $component->delete();
    }

    public function activate(Election $election, Ballot $ballot, BallotComponent $component): bool
    {
        $component->active = true;
        return $component->save();
    }

    public function deactivate(Election $election, Ballot $ballot, BallotComponent $component): bool
    {
        $component->active = false;
        $component->finished = true;
        return $component->save();
    }
}
