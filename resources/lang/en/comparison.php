<?php

/**
 * Owner-approved factual comparison content for the shared "how do these
 * methods compare" matrix served alongside every component's method
 * content. `labels` are the matrix's column headers; `disclaimer` is shown
 * once alongside the whole matrix (both live under `comparison_meta` in
 * `BallotService::getStatuteText()`'s payload, a top-level sibling of the
 * per-type entries — same pattern as `statute.quorum`).
 *
 * Per-type `elected` is the plain "number elected" descriptor for that
 * method (e.g. "1" for a single-winner method, "Multiple (top K)" for a
 * seats-based one). The 1-5 true_prefs/manipulation/simplicity RATINGS
 * themselves are structural facts about the method, not translated text,
 * so they live as ints hardcoded on the component class (see
 * `AbstractBallotComponent::getComparisonRatings()`), not here.
 *
 * Keyed by the same lowercase slugs as `components.php`/`academic.php`/
 * `manual.php`/`statute.php`. Keep resources/lang/sl/comparison.php in sync
 * (EN<->SL key parity).
 */
return [
    'labels' => [
        'elected' => 'Number elected',
        'true_prefs' => 'Captures true preferences',
        'manipulation' => 'Resistant to manipulation',
        'simplicity' => 'Easy to understand',
    ],

    'disclaimer' => 'A simplified educational comparison — each mark is a general characterization, not an absolute measure. No voting method with three or more options is completely immune to strategic voting.',

    'yesno' => [
        'elected' => 'Decision (pass/fail)',
    ],

    'fptp' => [
        'elected' => '1',
    ],

    'approval' => [
        'elected' => 'Multiple (top K)',
    ],

    'rankedchoice' => [
        'elected' => '1',
    ],

    'orderedlist' => [
        'elected' => 'Multiple, ranked (K)',
    ],
];
