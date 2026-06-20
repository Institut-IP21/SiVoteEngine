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
    /**
     * Per-type sample definitions. The branding page lets the organiser switch the
     * previewed question type (default Yes/No) so they see their logo + accent colour
     * on every kind of ballot. Each maps to its real component type + control style.
     *
     * Only types whose voter-facing control is faithfully a row of options are listed:
     * YesNo + FirstPastThePost (radios) and ApprovalVote (checkboxes). RankedChoice is
     * intentionally omitted — its real voter UI is a ranking interaction, so rendering
     * it as plain radios here would misrepresent it.
     *
     * @var array<string, array{label_key:string, control:string}>
     */
    private const SAMPLES = [
        'YesNo' => ['label_key' => 'ballot.preview.sample_motion', 'control' => 'radio'],
        'FirstPastThePost' => ['label_key' => 'ballot.preview.sample_question', 'control' => 'radio'],
        'ApprovalVote' => ['label_key' => 'ballot.preview.sample_question', 'control' => 'checkbox'],
    ];

    public function show(string $owner): Factory|View
    {
        $pers = Personalization::where('owner', $owner)->first();

        // The previewed question type is chosen on the branding page (?type=…),
        // defaulting to a Yes/No motion. Unknown values fall back to Yes/No.
        $type = (string) request()->query('type', 'YesNo');
        if (! array_key_exists($type, self::SAMPLES)) {
            $type = 'YesNo';
        }
        $sample = self::SAMPLES[$type];

        // Sample options, already localized for the org's locale (YesNo uses its real
        // preset labels; the others use generic localized "Option A/B/C"). Passed with
        // localize=false since they are final display strings.
        $options = $type === 'YesNo'
            ? [__('components.yesno.yes'), __('components.yesno.no')]
            : array_values((array) __('ballot.preview.sample_options'));

        // Transient (never-saved) sample. IDs are arbitrary; nothing is persisted.
        $election = new Election(['title' => 'Sample', 'abstainable' => false, 'owner' => $owner]);
        $election->id = 'preview-election';

        $ballot = new Ballot(['title' => __('ballot.single'), 'description' => null]);
        $ballot->id = 'preview-ballot';
        $ballot->election_id = $election->id;
        $ballot->setRelation('election', $election);

        $component = new BallotComponent([
            'title' => __($sample['label_key']),
            'description' => null,
            'type' => $type,
            'version' => 'v1',
            'options' => $options,
            'order' => 0,
        ]);
        $component->id = 'preview-component';
        $component->ballot_id = $ballot->id;

        // NB: passed as `sample`, not `component` — inside a Blade component slot
        // (x-ballot-wrapper) the name `$component` is reserved (the component instance).
        return view('branding-preview', [
            'pers' => $pers,
            'election' => $election,
            'ballot' => $ballot,
            'sample' => $component,
            'control' => $sample['control'],
            'localize' => false,
        ]);
    }
}
