<?php

namespace App\Http\Controllers;

use App\Http\Resources\BallotComponent as ComponentResource;
use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Services\BallotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BallotComponentApiController extends Controller
{
    protected BallotService $ballotService;

    public function __construct(BallotService $ballotService)
    {
        $this->ballotService = $ballotService;
    }

    /** @return array{data: array<mixed>} */
    public function list(Election $election): array
    {
        return [
            'data' => $this->ballotService->getComponentTree()
        ];
    }
    public function create(Election $election, Ballot $ballot, Request $request): JsonResource|JsonResponse
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
                function ($attribute, $value, $fail) {
                    if (!in_array($value, $this->ballotService->getBallotTypes())) {
                        $fail($attribute . ' must be a valid ballot type.');
                    }
                }
            ],
            'version' => [
                'required',
                'string',
                'bail',
                function ($attribute, $value, $fail) use ($params) {
                    if (!in_array($value, $this->ballotService->getBallotVersions($params['type']))) {
                        $fail($attribute . ' must be a valid version.');
                    }
                }
            ],
            'settings' => 'nullable|array',
            'settings.pass_threshold' => $this->passThresholdRule(),
        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        $class = $this->ballotService->getBallotComponentClass($params['type'], $params['version']);
        $secondaryValidation = [];

        if ($class::$needsOptions) {
            $secondaryValidation = array_merge($secondaryValidation, $class::$optionsValidator);
        }

        if ($errors = $this->findErrors($params, $secondaryValidation)) {
            return $errors;
        }

        $options = $class::$needsOptions ? $params['options'] : $class::$presetOptions;

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

    public function read(Election $election, Ballot $ballot, BallotComponent $component, Request $request): JsonResource
    {
        return new ComponentResource($component);
    }

    public function update(Election $election, Ballot $ballot, BallotComponent $component, Request $request): JsonResource|JsonResponse
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
                function ($attribute, $value, $fail) {
                    if (!in_array($value, $this->ballotService->getBallotTypes())) {
                        $fail($attribute . ' must be a valid ballot type.');
                    }
                }
            ],
            'version' => [
                'bail',
                'required',
                'string',
                function ($attribute, $value, $fail) use ($params) {
                    if (!in_array($value, $this->ballotService->getBallotVersions($params['type']))) {
                        $fail($attribute . ' must be a valid version.');
                    }
                }
            ],
            'settings' => 'nullable|array',
            'settings.pass_threshold' => $this->passThresholdRule(),
        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        $class = $this->ballotService->getBallotComponentClass($params['type'], $params['version']);
        $secondaryValidation = [];

        if (array_key_exists('options', $params) && $class::$needsOptions) {
            $secondaryValidation = array_merge($secondaryValidation, $class::$optionsValidator);
        }

        if ($errors = $this->findErrors($params, $secondaryValidation)) {
            return $errors;
        }

        if (array_key_exists('options', $params) && $class::$needsOptions) {
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
     * is a numeric value in [50,100] or one of the supported presets. Mirrors the
     * CLI's accepted set so the API and `BallotComponentCreate` stay in lockstep.
     *
     * @return array<int, mixed>
     */
    private function passThresholdRule(): array
    {
        return [
            'nullable',
            function ($attribute, $value, $fail) {
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
     * Build the `settings` payload from a request when a pass_threshold is supplied,
     * mirroring the CLI's normalisation (numeric -> int/float so it round-trips as a
     * number; preset strings pass through). Returns null when none is provided, so
     * callers can leave `settings` untouched (backward-compatible default of 50).
     *
     * @param array<string, mixed> $params
     * @return array{pass_threshold: int|float|string}|null
     */
    private function buildSettings(array $params): ?array
    {
        if (!isset($params['settings']) || !is_array($params['settings'])) {
            return null;
        }
        if (!array_key_exists('pass_threshold', $params['settings'])) {
            return null;
        }

        $value = $params['settings']['pass_threshold'];
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $value = $value + 0; // int|float
        }

        return ['pass_threshold' => $value];
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
