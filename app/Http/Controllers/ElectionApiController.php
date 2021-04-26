<?php

namespace App\Http\Controllers;

use App\Http\Resources\Election as ElectionResource;
use App\Models\Election;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @Controller(prefix="api/election")
 * @Middleware("api")
 */
class ElectionApiController extends Controller
{

    /**
     * @Get("/", as="election.list")
     * @Middleware("can:viewAny,App\Models\Election")
     */
    public function list(Request $request)
    {
        $params = $request->all();
        $settings = [
            'page' =>
            'integer',
            'size' =>
            'integer',
            'sort_by' =>
            'string|in:id,created_at,title|required_with:sort_direction',
            'sort_direction' =>
            'in:desc,asc|required_with:sort_by',
        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        $query = Election::where('owner', $this->getOwner());

        if (!empty($params['sort_by'])) {
            $query->orderBy(
                $params['sort_by'],
                $params['sort_direction']
            );
        }

        return ElectionResource::collection(
            $query
                ->paginate($params['size'] ?? 5)
                ->appends($params)
        );
    }

    /**
     * @Post("/create", as="election.create")
     * @Middleware("can:create,App\Models\Election")
     */
    public function create(Request $request)
    {
        $params = $request->all();
        $settings = [
            'title'         => 'required|string|min:5',
            'level'         => 'integer|required',
            'abstainable'   => 'nullable|boolean',
            'description'   => 'nullable|string',
            'title'         => 'required|string|min:5',
            'abstainable'   => 'nullable|boolean',
        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        $election_params = [
            'title' => $params['title'],
            'level' => $params['level'],
            'owner' => $this->getOwner(),
            'abstainable' => $params['abstainable'] ?? true,
            'description' => $params['description'] ?? '',
        ];

        $election = Election::create($election_params);

        return (new ElectionResource($election))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @Get("/{election}", as="election.read")
     * @Middleware("can:view,election")
     */
    public function read(Election $election, Request $request)
    {
        return new ElectionResource($election);
    }

    /**
     * @Post("/{election}", as="election.update")
     * @Middleware("can:update,election")
     */
    public function update(Election $election, Request $request)
    {
        $params = $request->all();
        $settings = [
            'title' => 'nullable|string|min:5',
            'abstainable' => 'nullable|boolean',
            'description' => 'nullable|string',
            'level' => 'integer'
        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        if (array_key_exists('abstainable', $params) && !$election->locked) {
            $election->abstainable = $params['abstainable'];
        }

        if (array_key_exists('title', $params)) {
            $election->title = $params['title'];
        }

        if (array_key_exists('description', $params)) {
            $election->description = $params['description'];
        }

        // todo maybe disallow changing level after it starts, or at all.
        if (array_key_exists('level', $params)) {
            $election->level = $params['level'];
        }

        $election->save();

        return new ElectionResource($election);
    }

    /**
     * @Delete("/{election}", as="election.delete")
     * @Middleware("can:delete,election")
     */
    public function delete(Election $election, Request $request)
    {
        return $election->delete();
    }
}
