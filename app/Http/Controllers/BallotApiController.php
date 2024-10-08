<?php

namespace App\Http\Controllers;

use App\Http\Resources\Ballot as BallotResource;
use App\Http\Resources\BallotComplete;
use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Services\BallotService;
use Illuminate\Http\Request;

class BallotApiController extends Controller
{
    private BallotService $ballotService;

    public function __construct(BallotService $ballotService)
    {
        $this->ballotService = $ballotService;
    }

    public function create(Election $election, Request $request)
    {
        $params = $request->all();
        $settings = [
            'title'          => 'required|string|min:5',
            'description'    => 'nullable|string|min:5',
            'email_template' => 'nullable|string|min:5',
            'email_subject'  => 'nullable|string|min:5',
            'is_secret'      => 'sometimes|boolean',
            'quorum'         => 'sometimes|integer',
            'mode'           => 'sometimes|string|in:' . implode(',', Ballot::MODES),
        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        if (($params['mode'] ?? '') === Ballot::MODE_SESSION) {
            $params['is_secret'] = false;
        }

        $election = Ballot::create([
            'election_id'    => $election->id,
            'description'    => $params['description'] ?? '',
            'email_template' => $params['email_template'] ?? '',
            'email_subject'  => $params['email_subject'] ?? '',
            'title'          => $params['title'],
            'is_secret'      => $params['is_secret'] ?? true,
            'quorum'         => $params['quorum'] ?? null,
            'mode'           => $params['mode'] ?? Ballot::MODE_BASIC,
        ]);

        return new BallotResource($election);
    }

    public function read(Election $election, Ballot $ballot, Request $request)
    {
        return new BallotResource($ballot);
    }

    public function result(Election $election, Ballot $ballot, Request $request)
    {
        if (!$ballot->finished) {
            return response(__('ballot.result.not_yet'), 403);
        }
        $results = $this->ballotService->calculateResults($ballot);
        return $results;
    }

    public function votes(Election $election, Ballot $ballot, Request $request)
    {
        if (!$ballot->finished) {
            return response(__('ballot.result.not_yet'), 400);
        }
        return new BallotComplete($ballot);
    }

    public function votesCsv(Election $election, Ballot $ballot, Request $request)
    {
        if (!$ballot->finished) {
            return response(__('ballot.result.not_yet'), 400);
        }
        $csv = $this->ballotService->resultsCsv($ballot);
        return response(['data' => $csv], 200);
    }

    public function update(Election $election, Ballot $ballot, Request $request)
    {
        if ($ballot->locked) {
            return $this->basicResponse(400, ['error' => __('This Ballot is locked and cannot be edited.')]);
        }

        $params = $request->all();
        $settings = [
            'title' => 'nullable|string|min:5',
            'description' => 'nullable|string|min:5',
            'email_template' => 'nullable|string|min:5',
            'email_subject' => 'nullable|string|min:5',
        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        if (array_key_exists('title', $params)) {
            $ballot->title = $params['title'];
        }

        if (array_key_exists('description', $params)) {
            $ballot->description = $params['description'];
        }

        if (array_key_exists('email_template', $params)) {
            $ballot->email_template = $params['email_template'];
        }

        if (array_key_exists('email_subject', $params)) {
            $ballot->email_subject = $params['email_subject'];
        }

        $ballot->save();

        return new BallotResource($ballot);
    }


    public function activate(Election $election, Ballot $ballot, Request $request)
    {
        if ($ballot->finished) {
            return $this->basicResponse(400, ['error' => __("This ballot is already finished. It cannot be reactivated.")]);
        }

        if (!$ballot->active) {
            $ballot->activate();
        }

        return new BallotResource($ballot);
    }

    public function deactivate(Election $election, Ballot $ballot, Request $request)
    {
        if ($ballot->active) {
            $ballot->deactivate();
        }

        return new BallotResource($ballot);
    }

    public function delete(Election $election, Ballot $ballot, Request $request)
    {
        if ($ballot->active) {
            return response('Active ballots cannot be deleted', 403);
        }

        return $ballot->delete();
    }

    public function switchOrder(Election $election, Ballot $ballot, Request $request)
    {
        if ($ballot->finished) {
            return response('Finished ballots can not be reordered', 403);
        }

        $params = $request->all();
        $settings = [
            'component1' => 'required|uuid',
            'component2' => 'required|uuid',
        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        $component1 = BallotComponent::find($params['component1']);
        $component2 = BallotComponent::find($params['component2']);

        $temp = $component1->order;

        $component1->order = $component2->order;
        $component2->order = $temp;
        $component1->save();
        $component2->save();

        return new BallotResource($ballot);
    }
}
