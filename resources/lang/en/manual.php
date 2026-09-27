<?php

/**
 * Bilingual by-hand calculation steps for each ballot component type: a
 * short, ordered, lay-readable procedure describing what a scrutineer with
 * paper would actually do to work out the result. Grounded in the REAL
 * calculator for each method (each type's `v1` component class's
 * `calculateResults()` and its supporting classes), including its specific
 * tie-break/quorum/threshold rules — not a textbook description of the
 * method. Parallel to `academic.php` (the neutral explanation + pros/cons)
 * and `statute.php` (the legal-reference clause text), but written as a
 * numbered procedure rather than a narrative explanation or a clause.
 *
 * Keyed by the same lowercase slugs as `components.php`/`academic.php`/
 * `statute.php` (yesno, fptp, rankedchoice, approval, orderedlist). Keep
 * resources/lang/sl/manual.php in sync (EN<->SL key parity).
 */
return [
    'yesno' => [
        'Sort every cast ballot into Yes, No, Abstain, or invalid; only the Yes and No ballots count toward the result.',
        'Add up the Yes ballots and the No ballots.',
        'If the two counts are equal, the motion fails outright — an equal count is never broken by a casting vote or a random draw.',
        'Otherwise, divide the Yes count by the sum of Yes and No to get the Yes share, and compare it against the pass threshold set for the vote: a simple majority (over half) by default, or two-thirds, three-quarters, or a custom percentage if the organization configured one.',
        'The motion passes only if Yes is ahead of No AND the Yes share meets that threshold.',
        'Separately, if the organization set a quorum, check the total number of ballots cast (not just Yes and No) against it: below quorum the same count is still carried out and shown in full, but only as advisory — it does not bind.',
    ],

    'fptp' => [
        'Sort each cast ballot to the option it marks; a blank ballot, an unrecognized answer, or (where abstention is allowed) an abstain token is set aside instead.',
        'Add up the valid votes for every listed option — every option starts the tally at zero, even ones nobody voted for.',
        'The option with the most votes wins. There is no minimum share or threshold to clear — a plain plurality is enough.',
        "If two or more options are tied for the most votes, the result is a tie: it is reported as such, not resolved by the engine — that is left to the organization's own rules.",
    ],

    'approval' => [
        "Sort each ballot's approvals: every option the voter marked receives one approval; a ballot that approves nothing is an abstention (where allowed) or invalid.",
        'Add up the approvals each option received — every listed option starts at zero.',
        'Rank the options from most approvals to fewest.',
        'Read off the top K options in that ranking, where K is the number of seats set for the vote (one, by default) — those options are elected.',
        "If two or more options are tied exactly at the cutoff for the last seat, and seating all of them would take more seats than remain, that seat is reported as contested rather than decided by the engine — the organization's own rules resolve it.",
    ],

    'rankedchoice' => [
        'Set aside blank ballots (nothing ranked) and invalid ballots (only options not on this ballot were ranked); every other ballot keeps counting, round after round, until it becomes exhausted.',
        'In each round, count every still-counted ballot for whichever surviving option is its highest-ranked continuing preference.',
        'If one option now has more than half of the continuing (still-counted) ballots, it wins outright.',
        'If exactly two options remain and neither has that majority, whichever has more votes in the round wins; an equal split between the final two is reported as a tie.',
        "Otherwise, eliminate the option(s) with the fewest votes in the round — every option tied at zero votes is eliminated together in one go — and move each of their ballots to its next preference still in the running. A ballot with no further ranked, continuing preference becomes exhausted and drops out of later rounds.",
        'When two or more options (not at zero) are tied for last place instead, look back to the most recent earlier round where their vote counts actually differed and eliminate whichever was lower there; if they were tied in every earlier round too, the tie is reported rather than resolved by the engine.',
        'Repeat from the second step until a winner is declared. The audit trail records, for every round, why it ended the way it did (majority reached, last-place elimination, look-back tie-break, and so on) plus how many ballots were cast, blank, invalid, or exhausted along the way.',
    ],

    'orderedlist' => [
        'For every ballot and every pair of candidates A and B, work out which one it prefers: if the voter approved A but not B (or ranked A ahead of B), the ballot prefers A; a candidate the voter did not approve at all gets no preference against another one they also did not approve.',
        'For each pair, compare how many ballots prefer A to B against how many prefer B to A. Whichever side has more has a "decisive" preference over the other, by that margin; an exact tie between the two counts is not decisive either way.',
        'For every pair of candidates, find the strongest chain of decisive preferences linking them — possibly running through other candidates as stepping stones (a "beatpath"). A candidate outranks another if its strongest chain toward them is stronger than the chain running the other way.',
        'Order every candidate from most- to least-outranking. A candidate who outranks every other candidate head-to-head — the Condorcet winner — always ends up first when one exists.',
        'The candidates in the top seats (the number of seats set for the vote — by default, all of them) are elected, in that order. Where the ranking cannot strictly place two or more candidates relative to each other, they share a tied band; if that band straddles the last seat, the cutoff is reported as contested rather than decided by the engine.',
        'If the organization set a category quota (a minimum or maximum for a labelled group, or an alternating "zipper" requirement) and marked it binding, it is applied afterwards by swapping in the fewest already-seated candidates needed to satisfy it. A quota marked advisory-only is still computed and reported alongside the result, but it never changes who is actually elected.',
    ],
];
