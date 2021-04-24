<?php

namespace App\Http\Controllers;

use App\Models\Ballot;
use App\Models\Election;
use App\Models\Personalization;
use App\Models\Vote;
use App\Services\BallotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @Controller(prefix="election")
 * @Middleware("web")
 */
class BallotController extends Controller
{
    private BallotService $ballotService;

    public function __construct(BallotService $ballotService)
    {
        $this->ballotService = $ballotService;
    }

    /**
     *  @Get("/{election}/ballot/{ballot}", as="ballot.show")
     */
    public function view(Election $election, Ballot $ballot, Request $request)
    {
        $code = $request->query('code');
        $vote = Vote::find($code);

        if (!$vote || !$vote->ballot->id === $ballot->id) {
            return view('404', ['code' => 404]);
        }

        if (!$ballot->active) {
            return view('ballot-expired', ['code' => 404]);
        }

        $code = $request->query('code');
        $pers = Personalization::where('owner', $election->owner)->first();
        return view('ballot', ['election' => $election, 'ballot' => $ballot, 'code' => $code, 'pers' => $pers]);
    }

    /**
     *  @Get("/{election}/ballot/{ballot}/preview", as="ballot.preview")
     *  @Middleware("signed")
     */
    public function preview(Election $election, Ballot $ballot, Request $request)
    {
        $pers = Personalization::where('owner', $election->owner)->first();
        return view('ballot-preview', ['election' => $election, 'ballot' => $ballot, 'pers' => $pers]);
    }

    /**
     *  @Post("/{election}/ballot/{ballot}", as="ballot.vote")
     */
    public function vote(Election $election, Ballot $ballot, Request $request)
    {
        $code = $request->input('code');
        $vote = Vote::find($code);

        if (!$vote || !$vote->ballot->id === $ballot->id) {
            return view('404', ['code' => 404]);
        }

        if (!$ballot->active) {
            return view('ballot-expired', ['code' => 404]);
        }

        $settings = array_merge([
            'code' => 'required|uuid|exists:App\Models\Vote,id',
        ], $this->ballotService->getSubmissionValidators($ballot));

        $validator = Validator::make($request->all(), $settings);
        $errors = $validator->errors();

        if (!$errors->isEmpty()) {
            return view('vote-failed', ['election' => $election, 'ballot' => $ballot, 'errors' => $errors]);
        }

        $code = $request->input('code');
        $values = $request->except(['code', '_token']); // Could get the component slugs and say ->only
        $vote = Vote::find(['code' => $code, 'ballot_id' => $ballot->id])->first();
        $vote->values = $values;
        $vote->save();

        $pers = Personalization::where('owner', $election->owner)->first();
        return view('voted', ['election' => $election, 'ballot' => $ballot, 'vote' => $vote, 'pers' => $pers]);
    }

    /**
     *  @Get("/{election}/ballot/{ballot}/result", as="ballot.result")
     */
    public function result(Election $election, Ballot $ballot, Request $request)
    {
        if (!$ballot->finished) {
            return response(__('ballot.result.not_yet'), 403);
        }
        $results = $this->ballotService->calculateResults($ballot);
        $pers = Personalization::where('owner', $election->owner)->first();

        return view('ballot-result', ['election' => $election, 'ballot' => $ballot, 'results' => $results, 'pers' => $pers]);
    }
}
