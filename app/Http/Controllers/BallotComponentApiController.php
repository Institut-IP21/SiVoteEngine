<?php

namespace App\Http\Controllers;

use App\Http\Resources\BallotComponent as ComponentResource;
use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Services\BallotService;
use Illuminate\Http\Request;

/**
 * @Controller(prefix="api/election/{election}/ballot/{ballot}/component")
 * @Middleware("api")
 */
class BallotComponentApiController extends Controller
{
    protected BallotService $ballotService;

    public function __construct(BallotService $ballotService)
    {
        $this->ballotService = $ballotService;
    }

    /**
     * @Get("/", as="component.list")
     * @Middleware("can:view,election")
     */
    public function list(Election $election)
    {
        return [
            'data' => $this->ballotService->getComponentTree()
        ];
    }
    /**
     * @Post("/create", as="component.create")
     * @Middleware("can:update,election")
     */
    public function create(Election $election, Ballot $ballot, Request $request)
    {
        if ($election->mode === Election::MODE_SESSION) {
            $ballot->loadCount('components');
            if ($ballot->components_count >= 1) {
                return $this->basicResponse(
                    400,
                    [
                        'error' => 'Only one component per ballot for SESSION elections is allowed.'
                    ]
                );
            }
        }

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

        $component = BallotComponent::create([
            'ballot_id' => $ballot->id,
            'description' => $params['description'] ?? '',
            'title' => $params['title'],
            'type' => $params['type'],
            'version' => $params['version'],
            'order' => $params['order'] ?? 0,
            'options' => $options,
        ]);

        return new ComponentResource($component);
    }

    /**
     * @Get("/{component}", as="component.read")
     * @Middleware("can:view,election")
     */
    public function read(Election $election, Ballot $ballot, BallotComponent $component, Request $request)
    {
        return new ComponentResource($component);
    }

    /**
     * @Post("/{component}", as="component.update")
     * @Middleware("can:update,election")
     */
    public function update(Election $election, Ballot $ballot, BallotComponent $component, Request $request)
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

        $component->save();

        return new ComponentResource($component);
    }

    /**
     * @Delete("/{component}", as="component.delete")
     * @Middleware("can:update,election")
     */
    public function delete(Election $election, Ballot $ballot, BallotComponent $component, Request $request)
    {
        return $component->delete();
    }
}
