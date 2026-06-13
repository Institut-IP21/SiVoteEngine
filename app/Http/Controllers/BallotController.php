<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;
use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Personalization;
use App\Models\Vote;
use App\Services\BallotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BallotController extends Controller
{
    public function __construct(private readonly BallotService $ballotService)
    {
    }

    /**
     * Render voter-facing pages in the locale the election was organized in.
     * These are public web routes (no SetLocale middleware), so without this
     * the ballot and results would always use the engine's default language.
     */
    private function applyElectionLocale(Election $election): void
    {
        if (!empty($election->locale)) {
            app()->setLocale($election->locale);
        }
    }

    /**
     * Map component ids to their human-readable titles so validation messages
     * reference the question (e.g. "Favorite colour?") instead of an opaque
     * component UUID. Keyed by component id; "code" is mapped to the vote-id
     * label.
     *
     * @return array<string, string>
     */
    private function validationAttributes(Ballot $ballot): array
    {
        // Use the relation query (not the $ballot->components accessor, which
        // returns a plain array) so we can pluck title keyed by id.
        $attributes = $ballot->components()
            ->pluck('title', 'id')
            ->filter()
            ->toArray();

        $attributes['code'] = __('ballot.voteId');

        return $attributes;
    }

    public function view(Election $election, Ballot $ballot, Request $request, BallotService $service): Factory|View
    {
        $this->applyElectionLocale($election);

        $code = $request->query('code');
        $vote = Vote::find($code);

        if (!$vote || $vote->ballot_id !== $ballot->id) {
            return view('404', ['code' => 404]);
        }

        if (!$ballot->active) {
            return view('ballot-expired', ['code' => 404]);
        }

        $componentTree = $service->getComponentTree();

        $code = $request->query('code');
        $pers = Personalization::where('owner', $election->owner)->first();
        return view('ballot', ['election' => $election, 'ballot' => $ballot, 'code' => $code, 'pers' => $pers, 'componentTree' => $componentTree]);
    }

    public function preview(Election $election, Ballot $ballot, Request $request, BallotService $service): Factory|View
    {
        $this->applyElectionLocale($election);

        $pers = Personalization::where('owner', $election->owner)->first();
        $componentTree = $service->getComponentTree();

        return view('ballot-preview', ['election' => $election, 'ballot' => $ballot, 'pers' => $pers, 'componentTree' => $componentTree]);
    }

    public function vote(Election $election, Ballot $ballot, Request $request): Factory|View
    {
        $this->applyElectionLocale($election);

        if ($ballot->mode === Ballot::MODE_SESSION) {
            throw new \Exception("Can not vote SESSION ballots this way ");
        }

        $code = $request->input('code');
        $vote = Vote::find($code);

        if (!$vote || $vote->ballot_id !== $ballot->id) {
            return view('404', ['code' => 404]);
        }

        if (!$ballot->active) {
            return view('ballot-expired', ['code' => 404]);
        }

        $settings = array_merge([
            'code' => 'required|uuid|exists:App\Models\Vote,id',
        ], $this->ballotService->getSubmissionValidators($ballot));

        $validator = Validator::make($request->all(), $settings, [], $this->validationAttributes($ballot));
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

    public function voteComponent(Election $election, Ballot $ballot, BallotComponent $component, Request $request)
    {
        $this->applyElectionLocale($election);

        if ($ballot->mode !== Ballot::MODE_SESSION) {
            throw new \Exception("Only SESSION ballots can vote this way");
        }

        $code = $request->input('code');
        $vote = Vote::find($code);

        if (!$vote || $vote->ballot_id !== $ballot->id) {
            return view('404', ['code' => 404]);
        }

        if (!$ballot->active) {
            return view('ballot-expired', ['code' => 404]);
        }

        $settings = array_merge([
            'code' => 'required|uuid|exists:App\Models\Vote,id',
        ], $this->ballotService->getPartialSubmissionValidators($ballot, $request->all()));

        $validator = Validator::make($request->all(), $settings, [], $this->validationAttributes($ballot));
        $errors = $validator->errors();

        if (!$errors->isEmpty()) {
            return view('vote-failed', ['election' => $election, 'ballot' => $ballot, 'errors' => $errors]);
        }

        $code = $request->input('code');
        $vote = Vote::find(['code' => $code, 'ballot_id' => $ballot->id])->first();

        $values = $request->except(['code', '_token']); // Could get the component slugs and say ->only
        $oldValues = $vote->values;
        if (!is_array($oldValues)) {
            $oldValues = [];
        }

        // Newly submitted values take precedence so a voter can change a
        // component answer (last-write-wins), matching basic-mode behaviour.
        $vote->values = array_merge($oldValues, $values);

        $vote->save();

        return redirect()->back()->with('success', __('ballot.vote.registered'));
    }

    public function result(Election $election, Ballot $ballot, Request $request): ResponseFactory|Response|Factory|View
    {
        $this->applyElectionLocale($election);

        if (!$ballot->finished) {
            return response(__('ballot.result.not_yet'), 403);
        }
        $results = $this->ballotService->calculateResults($ballot);
        $pers = Personalization::where('owner', $election->owner)->first();

        return view('ballot-result', ['election' => $election, 'ballot' => $ballot, 'results' => $results, 'pers' => $pers]);
    }
}
