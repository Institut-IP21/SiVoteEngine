<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Personalization;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;

/**
 * A faithful, data-free branding preview for the web_app branding page. It renders
 * the REAL voter-facing ballot shell (wrapper / logo / title / question card) with
 * the organizer's saved personalization and a small in-memory sample ballot — so the
 * organiser sees their logo + accent colour applied exactly as voters will, without
 * needing a real ballot to exist. Reuses the shell rather than re-drawing it.
 *
 * Owner is taken from the URL: branding (a logo + accent colour) is not secret, and
 * only a synthetic sample is shown — never any real election, ballot or vote.
 */
class BrandingPreviewController extends Controller
{
    public function show(string $owner): Factory|View
    {
        $pers = Personalization::where('owner', $owner)->first();

        // Transient (never-saved) sample. IDs are arbitrary; nothing is persisted.
        $election = new Election(['title' => 'Sample', 'abstainable' => false, 'owner' => $owner]);
        $election->id = 'preview-election';

        $ballot = new Ballot(['title' => __('ballot.single'), 'description' => null]);
        $ballot->id = 'preview-ballot';
        $ballot->election_id = $election->id;
        $ballot->setRelation('election', $election);

        $component = new BallotComponent([
            'title' => __('ballot.preview.sample_question'),
            'description' => null,
            'type' => 'FirstPastThePost',
            'version' => 'v1',
            'options' => ['Option A', 'Option B', 'Option C'],
            'order' => 0,
        ]);
        $component->id = 'preview-component';
        $component->ballot_id = $ballot->id;

        // NB: passed as `sample`, not `component` — inside a Blade component slot
        // (x-ballot-wrapper) the name `$component` is reserved (the component instance).
        return view('branding-preview', [
            'pers' => $pers,
            'ballot' => $ballot,
            'sample' => $component,
        ]);
    }
}
