<?php

namespace App\Http\Controllers;

use App\Http\Resources\Election as ElectionResource;
use App\Models\Election;
use Illuminate\Http\Request;

class ElectionApiController extends Controller
{

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
                ->paginate($params['size'] ?? 10)
                ->appends($params)
        );
    }

    public function create(Request $request)
    {
        $params = $request->all();
        $settings = [
            'title'         => 'required|string|min:5',
            'level'         => 'integer|required',
            'abstainable'   => 'nullable|boolean',
            'description'   => 'nullable|string'
        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        $election_params = [
            'title' => $params['title'],
            'level' => $params['level'],
            'owner' => $this->getOwner(),
            'abstainable' => $params['abstainable'] ?? true,
            'description' => $params['description'] ?? ''
        ];

        $election = Election::create($election_params);

        return (new ElectionResource($election))
            ->response()
            ->setStatusCode(201);
    }

    public function read(Election $election)
    {
        return new ElectionResource($election);
    }

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

        if (array_key_exists('level', $params)) {
            if ($election->locked) {
                return response()->json([
                    'error' => 'Cannot change level of a locked election.'
                ], 400);
            }
            $election->level = $params['level'];
        }

        $election->save();

        return new ElectionResource($election);
    }

    public function delete(Election $election, Request $request)
    {
        return $election->delete();
    }
}
