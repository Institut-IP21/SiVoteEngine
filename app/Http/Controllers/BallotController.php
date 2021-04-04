<?php

namespace App\Http\Controllers;

use App\Models\Ballot;
use App\Models\Election;
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
    public function single(Election $election, Ballot $ballot, Request $request)
    {
        $code = $request->query('code');
        $settings = ['code' => 'required|uuid|exists:App\Models\Vote,id'];
        $validator = Validator::make(['code' => $code], $settings);
        $errors = $validator->errors();

        if (!$errors->isEmpty()) {
            return view('404');
        }

        return view('ballot', ['election' => $election, 'ballot' => $ballot, 'code' => $code]);
    }

    /**
     *  @Get("/{election}/ballot/{ballot}/preview", as="ballot.preview")
     */
    public function preview(Election $election, Ballot $ballot, Request $request)
    {
        return view('ballot-preview', ['election' => $election, 'ballot' => $ballot]);
    }

    /**
     *  @Post("/{election}/ballot/{ballot}", as="ballot.vote")
     */
    public function vote(Election $election, Ballot $ballot, Request $request)
    {
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

        return view('voted', ['election' => $election, 'ballot' => $ballot, 'vote' => $vote]);
    }

    /**
     *  @Get("/{election}/ballot/{ballot}/result", as="ballot.result")
     */
    public function result(Election $election, Ballot $ballot, Request $request)
    {
        if (!$ballot->finished) {
            return response('Ballot results not available yet', 403);
        }
        $results = $this->ballotService->calculateResults($ballot);

        return view('ballot-status', ['election' => $election, 'ballot' => $ballot, 'results' => $results]);
    }
}
