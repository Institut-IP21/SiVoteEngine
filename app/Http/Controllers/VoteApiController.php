<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ApiAuth;
use App\Models\Ballot;
use App\Models\Election;
use App\Models\Vote;
use App\Services\BallotService;
use App\Services\VoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoteApiController extends Controller
{
    public function __construct(protected BallotService $ballotService)
    {
    }

    /**
     * The vote id is the voting code: list it only before the ballot opens, or to an admin.
     *
     * @return array<int, string>|JsonResponse
     */
    public function show(Election $election, Ballot $ballot, Request $request): array|JsonResponse
    {
        $isAdmin = $request->attributes->get(ApiAuth::ADMIN_ATTRIBUTE) === true;
        $notYetOpened = !$ballot->active && !$ballot->finished;

        if ($notYetOpened || $isAdmin) {
            return array_map(fn(array $vote) => $vote['id'], $ballot->votes()->get()->toArray());
        }

        return response()->json([
            'electorate_size' => $ballot->electorate_size,
            'votes_count' => $ballot->votes_count,
        ]);
    }

    /** @return array<mixed>|JsonResponse */
    public function generate(Election $election, Ballot $ballot, Request $request, VoteService $voteService): array|JsonResponse
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
