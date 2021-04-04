<?php

namespace App\Http\Controllers;

use App\Http\Resources\Election as ElectionResource;
use App\Models\Ballot;
use App\Models\Election;
use App\Models\Vote;
use App\Services\BallotService;
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
    public function generate(Election $election, Ballot $ballot, Request $request)
    {
        $params = $request->all();
        $settings = [
            'quantity' => 'required|integer|min:1|max:10000'
        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        $now = date('Y-m-d H:i:s');
        for ($i = 0; $i < $params['quantity']; $i++) {
            $vote = Vote::create(['ballot_id' => $ballot->id, 'created_at' => $now]);
            $codes[] = $vote->id;
        }
        return $codes;
    }
}
