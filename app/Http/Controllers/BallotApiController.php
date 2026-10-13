<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;
use App\Http\Resources\Ballot as BallotResource;
use App\Http\Resources\BallotComplete;
use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Services\BallotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BallotApiController extends Controller
{
    public function __construct(private readonly BallotService $ballotService)
    {
    }

    public function create(Election $election, Request $request): JsonResponse|BallotResource
    {
        $params = $request->all();
        $settings = [
            'title'          => 'required|string|min:5',
            'description'    => 'nullable|string|min:5',
            'email_template' => 'nullable|string|min:5',
            'email_subject'  => 'nullable|string|min:5',
            'result_email_template' => 'nullable|string|min:5',
            'result_email_subject'  => 'nullable|string|min:5',
            'is_secret'      => 'sometimes|boolean',
            // D11: a quorum, when set, must be a positive turnout threshold.
            'quorum'         => 'sometimes|nullable|integer|min:1',
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
            'result_email_template' => $params['result_email_template'] ?? '',
            'result_email_subject'  => $params['result_email_subject'] ?? '',
            'title'          => $params['title'],
            'is_secret'      => $params['is_secret'] ?? true,
            'quorum'         => $params['quorum'] ?? null,
            'mode'           => $params['mode'] ?? Ballot::MODE_BASIC,
        ]);

        return new BallotResource($election);
    }

    public function read(Election $election, Ballot $ballot, Request $request): BallotResource
    {
        return new BallotResource($ballot);
    }

    /**
     * @return array<string, array<string, mixed>>|ResponseFactory|Response
     */
    public function result(Election $election, Ballot $ballot, Request $request): ResponseFactory|Response|array
    {
        if (!$ballot->finished) {
            return response(__('ballot.result.not_yet'), 403);
        }
        $results = $this->ballotService->calculateResults($ballot);
        return $results;
    }

    public function votes(Election $election, Ballot $ballot, Request $request): ResponseFactory|Response|BallotComplete
    {
        if (!$ballot->finished) {
            return response(__('ballot.result.not_yet'), 400);
        }
        return new BallotComplete($ballot);
    }

    public function votesCsv(Election $election, Ballot $ballot, Request $request): ResponseFactory|Response
    {
        if (!$ballot->finished) {
            return response(__('ballot.result.not_yet'), 400);
        }
        $csv = $this->ballotService->resultsCsv($ballot);
        return response(['data' => $csv], 200);
    }

    /**
     * The TALLIED outcome as CSV (who's elected/contested per question), as
     * opposed to votesCsv()'s raw per-vote export.
     */
    public function resultTallyCsv(Election $election, Ballot $ballot, Request $request): ResponseFactory|Response
    {
        if (!$ballot->finished) {
            return response(__('ballot.result.not_yet'), 400);
        }
        $csv = $this->ballotService->resultsTallyCsv($ballot);
        return response(['data' => $csv], 200);
    }

    public function update(Election $election, Ballot $ballot, Request $request): JsonResponse|BallotResource
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
            'result_email_template' => 'nullable|string|min:5',
            'result_email_subject' => 'nullable|string|min:5',
            'quorum' => 'nullable|integer|min:1',
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

        if (array_key_exists('result_email_template', $params)) {
            $ballot->result_email_template = $params['result_email_template'];
        }

        if (array_key_exists('result_email_subject', $params)) {
            $ballot->result_email_subject = $params['result_email_subject'];
        }

        if (array_key_exists('quorum', $params)) {
            $ballot->quorum = $params['quorum'];
        }

        $ballot->save();

        return new BallotResource($ballot);
    }


    public function activate(Election $election, Ballot $ballot, Request $request): JsonResponse|BallotResource
    {
        if ($ballot->finished) {
            return $this->basicResponse(400, ['error' => __("This ballot is already finished. It cannot be reactivated.")]);
        }

        if (!$ballot->active) {
            $ballot->activate();
        }

        return new BallotResource($ballot);
    }

    public function deactivate(Election $election, Ballot $ballot, Request $request): JsonResponse|BallotResource
    {
        // Lock + re-check so a deactivate racing a rollback cannot finish a ballot
        // whose codes were just deleted.
        $refused = DB::transaction(function () use ($ballot): ?JsonResponse {
            $locked = Ballot::whereKey($ballot->id)->lockForUpdate()->firstOrFail();

            if (!$locked->active || $locked->finished) {
                return $this->basicResponse(409, ['error' => __('This ballot is not open, so it cannot be closed.')]);
            }

            // State verified under the lock; apply to the bound instance so the
            // response reflects it.
            $ballot->deactivate();

            return null;
        });

        if ($refused !== null) {
            return $refused;
        }

        return new BallotResource($ballot);
    }

    /**
     * Undo a failed launch (activate and/or generate codes): put a ballot with
     * no cast votes (open or still a draft) back to a never-opened draft. Atomic under a row lock so a
     * vote cannot slip in between the check and the delete.
     */
    public function rollbackLaunch(Election $election, Ballot $ballot, Request $request): JsonResponse|BallotResource
    {
        $refused = DB::transaction(function () use ($ballot): ?JsonResponse {
            $locked = Ballot::whereKey($ballot->id)->lockForUpdate()->firstOrFail();

            if ($locked->finished) {
                return $this->basicResponse(409, ['error' => __('This ballot is already finished. Its launch cannot be rolled back.')]);
            }

            if ($locked->votes()->whereNotNull('values')->exists()) {
                return $this->basicResponse(409, ['error' => __('A vote has already been cast. The launch cannot be rolled back.')]);
            }

            // Hard-delete (not soft): leftover codes must not stay valid anywhere.
            $locked->votes()->withTrashed()->forceDelete();
            $locked->active = false;
            $locked->opened_at = null;
            $locked->save();

            return null;
        }, attempts: 3);

        if ($refused !== null) {
            return $refused;
        }

        return new BallotResource($ballot->refresh());
    }

    public function delete(Election $election, Ballot $ballot, Request $request): Response|bool|null
    {
        if ($ballot->active) {
            return response('Active ballots cannot be deleted', 403);
        }

        return $ballot->delete();
    }

    public function switchOrder(Election $election, Ballot $ballot, Request $request): ResponseFactory|Response|JsonResponse|BallotResource
    {
        if ($ballot->locked) {
            return response(['error' => __('This Ballot is locked and cannot be edited.')], 409);
        }

        $params = $request->all();
        $settings = [
            'component1' => 'required|uuid',
            'component2' => 'required|uuid',
        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        /** @var BallotComponent $component1 */
        $component1 = BallotComponent::where('ballot_id', $ballot->id)->findOrFail($params['component1']);
        /** @var BallotComponent $component2 */
        $component2 = BallotComponent::where('ballot_id', $ballot->id)->findOrFail($params['component2']);

        $temp = $component1->order;

        $component1->order = $component2->order;
        $component2->order = $temp;
        $component1->save();
        $component2->save();

        return new BallotResource($ballot);
    }
}
