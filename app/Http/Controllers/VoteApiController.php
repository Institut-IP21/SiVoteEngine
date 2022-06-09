<?php

namespace App\Http\Controllers;

use App\Models\Ballot;
use App\Models\Election;
use App\Models\Vote;
use App\Services\BallotService;
use App\Services\VoteService;
use Illuminate\Http\Request;

/**
 * @Controller(prefix="api/election/{election}/ballot/{ballot}/vote")
 * @Middleware("api")
 */
class VoteApiController extends Controller
{
    protected BallotService $ballotService;

    public function __construct(BallotService $ballotService)
    {
        $this->ballotService = $ballotService;
    }

    /**
     * @Get("/", as="vote.show")
     * @Middleware("can:view,election")
     */
    public function show(Election $election, Ballot $ballot, Request $request)
    {
        return array_map(function ($vote) {
            return $vote['id'];
        }, $ballot->votes()->get()->toArray());
    }
    /**
     * @Post("/generate", as="vote.generate")
     * @Middleware("can:update,election")
     */
    public function generate(Election $election, Ballot $ballot, Request $request, VoteService $voteService)
    {
        $params = $request->all();

        if ($ballot->is_secret) {
            $settings = [
                'quantity' => 'required|integer|min:1|max:10000'
            ];
        } else {
            $settings = [
                'voters' => 'required|array|min:1|max:10000'
            ];
        }

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        if ($ballot->is_secret) {
            $codes = $voteService->generateSecretVotes($ballot, $params['quantity']);
        } else {
            $codes = $voteService->generatePublicVotes($election, $ballot, $params['voters']);
        }

        return $codes;
    }
}
