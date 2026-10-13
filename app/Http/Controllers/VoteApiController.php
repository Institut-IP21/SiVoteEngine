<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ApiAuth;
use App\Exceptions\BallotNotActiveException;
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

        // Codes are minted exactly once per ballot. The check and the inserts run in
        // one transaction under a row lock on the ballot, so two concurrent calls
        // cannot both pass the guard. The ballot must be active (re-checked under the
        // lock) so a racing rollback-launch cannot leave codes on a draft.
        try {
            $codes = $voteService->mintOnce($ballot, fn (Ballot $locked): array => $locked->is_secret
                ? $voteService->generateSecretVotes($locked, $params['quantity'])
                : $voteService->generatePublicVotes($election, $locked, $params['voters']));
        } catch (BallotNotActiveException) {
            return $this->basicResponse(409, [
                'error' => __('The ballot must be active before voting codes can be generated.'),
            ]);
        }

        if ($codes === null) {
            return $this->basicResponse(409, [
                'error' => __('Voting codes have already been generated for this ballot.'),
            ]);
        }

        return $codes;
    }
}
