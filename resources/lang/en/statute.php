<?php

/**
 * Bilingual statute/legal-reference clause text served by the statute API
 * (see local_docs/voting-components/statute-feature-spec.md and
 * statute-content-draft.md, the source this file transcribes). Each key
 * below is an ORDERED LIST of clause paragraphs; the numbering ("(1)",
 * "(2)", ...) is generated for display from array order and is never
 * stored here. `quorum` is the shared preamble, drafted once and shown
 * above every method's clauses (D11) — not duplicated into the 5 arrays.
 *
 * Static, generic reference text (D4) — no per-ballot value interpolation.
 */
return [
    'quorum' => [
        'Quorum is a separate, optional requirement that the organization may set for a given vote. Where no quorum has been set for a vote, the outcome of that vote is not conditioned on turnout at all.',
        'Where a quorum has been set, it is measured against turnout — the number of ballots actually cast in that vote — and not against the size of the electorate entitled to vote. A ballot counts toward quorum once cast, independently of whether the votes recorded on it are later found valid under the particular method\'s own rules.',
        'Quorum is met when the number of ballots cast is at least the quorum number set for the vote.',
        'Where quorum is not met, the count nonetheless proceeds and is computed and recorded in full; only its binding effect is withheld. The result in that case is advisory only — it is never suppressed, discarded, or left uncomputed on account of quorum not being met.',
    ],

    'yesno' => [
        'Each eligible voter casts one ballot on the proposition, marked "Yes" or "No"; where the ballot permits abstention, the voter may instead mark "Abstain".',
        'The proposition is carried if "Yes" votes outnumber "No" votes among the votes validly cast for the proposition, and the share of "Yes" votes among those votes reaches the threshold set for the vote. The threshold is a simple majority — more than half of the votes validly cast — unless the organization has set a qualified-majority threshold for the vote, such as two-thirds or three-quarters; the threshold applied to a vote is never lower than a simple majority.',
        'An "Abstain" vote, where the ballot permits it, and any blank or otherwise invalid vote, are excluded from the votes counted under point (2); the outcome is determined solely by the "Yes" and "No" votes validly cast.',
        'Where the number of "Yes" votes equals the number of "No" votes, the proposition is not carried, whatever threshold applies. This outcome is not altered by any casting vote of a presiding officer and is not subject to any random draw; an equal count is, of itself, a final and sufficient reason the proposition fails.',
        'Whether this vote is subject to quorum, and the effect of quorum not being met, is governed by the Quorum clause, which applies uniformly to every vote under this statute.',
    ],

    'fptp' => [
        'Each eligible voter casts one vote for one of the listed options; where the ballot permits abstention, the voter may instead abstain.',
        'The option that receives the greatest number of votes among the votes validly cast is elected. No minimum share or threshold of the votes cast is required for election; the outcome is determined solely by which option received the most votes.',
        'A blank vote is treated as an abstention where the ballot permits abstention, and as invalid otherwise; a vote for an option not on the ballot, or submitted in any form other than a single selection, is invalid. Abstentions and invalid votes are not counted toward any option\'s total under point (2).',
        'Where two or more options receive an equal number of votes, and that number is the greatest received by any option, the vote results in a tie among those options. The tie is not resolved by any casting vote or by any random draw performed by the voting system; it is surfaced as a tie, to be resolved according to the organization\'s own rules of procedure (such as a drawing of lots, a further vote among the tied options, or a decision of the competent body).',
        'Whether this vote is subject to quorum, and the effect of quorum not being met, is governed by the Quorum clause.',
    ],

    'rankedchoice' => [
        'Each eligible voter casts one ballot ranking the candidates for the single seat in order of preference. A voter need not rank every candidate, but may not give the same rank to two candidates. A ballot on which the voter has ranked no candidate is blank, and is treated as an abstention where the ballot permits abstention, and otherwise as invalid.',
        'The count proceeds in rounds. In each round, every ballot still counted is counted for whichever candidate is its highest-ranked candidate among those still in contention. A candidate who is that highest continuing preference on more than half of the ballots still counted in that round is elected.',
        'If no candidate has reached that majority and more than two candidates remain in contention, the candidate with the fewest votes in that round is eliminated, and every ballot counted for that candidate is transferred to count instead for whichever remaining candidate is its next-ranked continuing preference. A ballot with no further ranked, continuing preference is exhausted and is not counted in any later round.',
        'The count under points (2)–(3) repeats, round by round, until either a candidate reaches the majority described in point (2), or only two candidates remain in contention. Where only two remain, whichever has more votes in that round is elected; where they have an equal number of votes, point (6) applies.',
        'Where two or more candidates are tied for fewest votes in a round under point (3), the candidate to be eliminated is determined by looking back to the most recent earlier round in which the tied candidates\' vote counts differed, and eliminating whichever of them had fewer votes at that earlier point. This rule is applied automatically and consistently by the voting system; it does not involve any random draw.',
        'Where, at a point the count would otherwise conclude under point (2) or (4), two or more candidates remain tied with each other — whether tied for election between the last two candidates, or tied in a way point (5) cannot resolve because their vote counts were identical in every earlier round too — no candidate is elected. The tie is surfaced among the candidates concerned and is resolved according to the organization\'s own rules of procedure.',
        'A blank ballot, and a ballot none of whose ranked candidates remain in contention, are not counted in any round; the majority described in point (2) is computed only over the ballots still counted in that round, not over the total number of ballots cast.',
        'Whether this vote is subject to quorum, and the effect of quorum not being met, is governed by the Quorum clause.',
    ],

    'approval' => [
        'Each eligible voter may cast an approval for any number of the listed options, from none up to all of them; a voter is not limited to a single choice, and approving one option does not withhold approval from any other.',
        'The options elected are those with the greatest number of approvals among the votes validly cast, up to the number of seats set for the vote (by default, one). Where fewer options are on the ballot than the number of seats set, every listed option is elected. No minimum share or threshold of approvals is required of any option; each seat is filled solely by relative ranking of the approvals received.',
        'A voter who casts no approval on the ballot is treated as an abstention where the ballot permits abstention, and otherwise as invalid; an approval for an option not on the ballot, or submitted in a form other than a valid approval, is invalid and is not counted toward that option\'s total under point (2).',
        'Where the number of approvals received by two or more options is equal at the boundary between the last elected seat and the first unelected option — such that it cannot be determined from the approvals alone which of them fills the remaining seat or seats — the tie is surfaced among those options. It is not resolved by any casting vote or by any random draw performed by the voting system; it is resolved according to the organization\'s own rules of procedure (such as a drawing of lots, a further vote among the tied options, or a decision of the competent body).',
        'Whether this vote is subject to quorum, and the effect of quorum not being met, is governed by the Quorum clause.',
    ],

    'orderedlist' => [
        'Each eligible voter casts one ballot naming, from the options listed, the subset the voter approves, and ordering that subset by the voter\'s preference. An option not named by the voter is not approved by that voter. A ballot on which the voter approves no option is blank, and is treated as an abstention where the ballot permits abstention, and otherwise as invalid.',
        'For the purposes of tabulation, a ballot is treated as preferring, of any two options A and B, whichever the voter approved if the other was not approved, or, where the voter approved both, whichever the voter placed ahead of the other. A ballot that approves neither A nor B expresses no preference between them.',
        'The options are compared pairwise: for each pair of options A and B, the number of ballots preferring A to B (as described in point (2)) is compared with the number preferring B to A. If more ballots prefer A to B than prefer B to A, A has a decisive preference over B, by the margin of that difference; if the two numbers are equal, neither option has a decisive preference over the other from that comparison alone.',
        'Option A is ranked ahead of option B in the result if the strongest chain of decisive preferences running from A to B — through any number of intermediate options, including, as the simplest case, a direct decisive preference between A and B with no intermediate options, where each link in the chain is itself a decisive preference under point (3) — is stronger (that is, has a greater margin at its weakest link) than the strongest such chain running from B to A. Where neither direction has a chain stronger than the other\'s — including where neither has any such chain at all — A and B are genuinely tied, and neither is ranked ahead of the other.',
        'The options are ordered from most to least preferred according to point (4); the options placed within the number of seats set for the vote are elected, in that order (by default, the number of seats equals the number of options on the ballot, so every option is elected and ordered).',
        'Where point (4) cannot place two or more options in a strict order relative to each other — because they are genuinely tied, or because a chain of ties links them together — those options form a single group sharing the positions they occupy between them. Where such a group straddles the last seat to be filled, it is not possible to determine from the votes alone which of the group\'s options fill the remaining seat or seats, and this is surfaced as a tie. The tie is not resolved by any casting vote or by any random draw performed by the voting system; it is resolved according to the organization\'s own rules of procedure (such as a drawing of lots, a further vote among the tied options, or a decision of the competent body). The same applies to a tie affecting only the relative order among options that are, in either case, already elected.',
        'The organization may, for a given vote, set a composition requirement: a minimum or maximum number of the seats filled under points (4)–(6) that must be held by options belonging to a stated category (for example, a minimum number of seats held by options from a particular labelled group). Where a composition requirement is set and would not otherwise be met, the elected options are adjusted by replacing the fewest options necessary — those nearest the seat boundary — with options from the required category, while otherwise preserving the order under point (4) as closely as possible. This composition requirement is a rule about the category each elected option belongs to; it does not allocate, transfer, or recompute any votes, and must not be confused with any vote-based quota mechanism.',
        'Where satisfying a composition requirement under point (7) would require displacing options whose relative order is itself tied under point (6), or where the seats affected by the requirement are themselves part of an unresolved tie at the seat boundary, the adjustment is not made; instead, the composition requirement is likewise surfaced as unresolved pending resolution of the tie under point (6). Where the organization has set the composition requirement to be advisory rather than binding, the elected options are never adjusted for it; the requirement then serves only as a reported observation on the result actually produced under points (4)–(6).',
        'Whether this vote is subject to quorum, and the effect of quorum not being met, is governed by the Quorum clause.',
    ],
];
