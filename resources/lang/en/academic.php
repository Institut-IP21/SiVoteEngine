<?php

/**
 * Bilingual academic/educational explainer content for each ballot component
 * type: a short, neutral explanation plus a handful of pros/cons bullets.
 * Migrated from web_app's `resources/lang/en/academic.php` so this content is
 * centralized in the engine (the single source of truth) instead of
 * duplicated app-side — parallel to the legal statute clauses in
 * `statute.php`. Background/educational reading, not the organization's
 * actual rules, and not the short voter-facing copy in `components.php`'s
 * `lay_explanation` keys.
 *
 * Keyed by the same lowercase slugs as `components.php`/`statute.php`
 * (yesno, fptp, rankedchoice, approval, orderedlist) rather than web_app's
 * PascalCase `ComponentType` enum names. Keep resources/lang/sl/academic.php
 * in sync (EN<->SL key parity).
 */
return [
    'yesno' => [
        'explanation' => 'A yes/no vote asks members to accept or reject a single proposal, with the outcome decided by whether the Yes share clears a required threshold (a simple majority, unless the organization set a higher one). It is the most direct form of collective decision-making: one question, two possible answers.',
        'pros' => [
            'Simple to understand, to vote on, and to count — there is no ranking or ballot design to get wrong.',
            'Gives a clear, decisive mandate: the proposal either passes or it does not.',
            'Keeps the focus on one question at a time, so unrelated issues cannot be traded off against each other.',
        ],
        'cons' => [
            "Collapses nuanced or mixed opinions into a single binary choice, with no room to express how strongly someone feels.",
            'The result depends heavily on how the question is worded and framed by whoever proposes it.',
            "Offers no information about voters' second-best preferences if the proposal fails.",
        ],
    ],

    'fptp' => [
        'explanation' => "First-past-the-post asks each voter to mark a single favourite among the options; whichever option collects the most votes wins, even without a majority. It is the classic 'choose one' ballot used for single-winner elections.",
        'pros' => [
            'Very easy to vote (mark one box) and to count.',
            'Always produces a single, decisive winner without further rounds.',
            'Familiar to most voters, which supports confidence in the result.',
        ],
        'cons' => [
            'A winner can take the seat with well under half the votes when several similar options split the vote between them.',
            'Can push voters toward "tactical" voting for a viable option rather than their honest favourite.',
            'Captures nothing about how voters would have ranked the other options.',
        ],
    ],

    'rankedchoice' => [
        'explanation' => "Ranked-choice voting (instant-runoff) asks voters to rank the options in order of preference. If no option has a majority of first choices, the option with the fewest is eliminated and its votes move to each ballot's next preference; this repeats until one option has a majority.",
        'pros' => [
            'Guarantees the winner has majority support among the remaining options, not just a plurality.',
            'Voters can rank a less-popular favourite first without "wasting" their vote, since it can transfer if that option is eliminated.',
            'Softens the spoiler effect that plagues single-mark plurality voting.',
        ],
        'cons' => [
            'The counting process is harder to follow than a simple tally — a genuine tradeoff for transparency.',
            'Vote transfers can, in rare cases, behave counter-intuitively: ranking a candidate higher can occasionally contribute to that candidate losing.',
            'Broadly-acceptable moderate options can be eliminated early in a "centre squeeze" if they lack enough first-choice support.',
        ],
    ],

    'approval' => [
        'explanation' => 'Approval voting lets each voter mark every option they find acceptable, rather than picking just one; the options with the most approvals fill the available seats. It suits selecting several winners at once (a committee, a shortlist) from a shared pool of candidates.',
        'pros' => [
            'Voters can support several acceptable options at once, without being forced to rank or exclude the rest.',
            'Reduces the "spoiler" effect of first-past-the-post, since approving a similar option never costs a preferred one a vote.',
            'The ballot itself stays simple — mark as many as you like.',
        ],
        'cons' => [
            'Does not capture how strongly a voter prefers one approved option over another.',
            'Outcomes are sensitive to where each voter personally draws the line between "acceptable" and "not" — a strategic choice in itself.',
            'A broadly tolerable option can outscore one that is more intensely preferred by a smaller group.',
        ],
    ],

    'orderedlist' => [
        'explanation' => "The ordered-list method ranks candidates by comparing every pair of them head-to-head across all ballots, producing a full ordered slate rather than a single winner — useful for filling several seats in priority order. (SiVote's implementation follows the Schulze / beatpath method.)",
        'pros' => [
            'Elects the option that would beat every other option head-to-head, whenever such an option exists — a strong fairness guarantee.',
            'More resistant than simpler methods to a voter gaining an advantage by mis-stating their true preferences.',
            'Produces a complete ordered outcome, which suits filling multiple seats by rank rather than picking one winner.',
        ],
        'cons' => [
            'The pairwise comparison behind the result is mathematically involved and hard to verify by hand.',
            'Circular preferences among voters (nobody beats everybody) can still require a tie-break rule for the last seat.',
            'Asking voters to rank every candidate is a heavier ask than a single mark or an approval list.',
        ],
    ],
];
