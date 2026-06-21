<?php

return [
    'option' => 'Option',
    'votes' => 'Votes',
    'winner' => 'Winner',
    'eliminated' => 'Eliminated',
    'tie' => 'Tie',
    'oftotal' => 'Share (%)',
    'share_valid' => 'Share of valid votes (%)',
    'yesno' => [
        'yes' => 'Yes',
        'no' => 'No',
        'abstain' => 'Abstain',
        'hint' => 'Choose one option.',
        'name' => 'Yes/No question',
        'tie' => 'The outcome is a tie.',
        'description' => 'Voters choose whether they support or oppose a single item.',
        'invalid' => 'Invalid',
        'carried' => 'Motion carried',
        'not_carried' => 'Motion not carried',
        'not_carried_tied' => 'Motion not carried (tied :yes–:no)'
    ],
    'fptp' => [
        'name' => 'First past the post / Plurality question',
        'abstain' => 'Abstain',
        'hint' => 'Choose one candidate.',
        'invalid' => 'Invalid',
        'tie' => 'The outcome is a tie. The following options received the same number of votes: ',
        'description' => 'Voters choose one item from a list of options.'
    ],
    'rankedchoice' => [
        'name' => 'Ranked choice question',
        'description' => 'Voters rank multiple options in order of their preference.',
        'hint' => 'Tap in order — 1 = favourite. Rank as many or as few as you like; you need not rank all.',
        'abstain_note' => 'No options ranked — submitting this question blank counts as abstaining.',
        'counter' => 'Ranked: :selected of :total',
        'remaining' => 'Remaining options',
        'add' => 'Add :name to your ranking',
        'add_short' => 'Add',
        'preview_static' => 'Drag-to-rank is interactive on the live ballot.',
        'move_up' => 'Move :name up',
        'move_down' => 'Move :name down',
        'move_top' => 'Move :name to the top',
        'remove' => 'Remove :name from your ranking',
        'position' => ':name, choice :rank of :total',
        'announce_added' => ':name added as choice :rank',
        'announce_removed' => ':name removed from your ranking',
        'announce_moved' => ':name, now choice :rank',
        'drag' => 'Drag to reorder',
        'requires_js' => 'Ranking this question requires JavaScript. Please enable it and refresh the page.',
        'round' => 'Round',
        'continuing' => 'Continuing ballots',
        'exhausted' => 'Exhausted ballots',
        // Result-first display (progressive disclosure).
        'final_standing' => 'Final standing',
        'winner_headline' => 'Winner: :name',
        'no_winner_headline' => 'No outright winner',
        'provisional_leader' => 'Provisional leader: :name',
        'outcome_majority' => 'Won outright with a majority of first preferences (:pct%).',
        'outcome_after_rounds' => 'Won after :rounds counting rounds: as the lowest options were eliminated, their votes transferred to voters’ next choice until :name passed 50%.',
        'outcome_tie' => 'No option reached a majority — :names tied at :pct%.',
        'no_majority' => 'No option reached a majority.',
        'outcome_not_binding' => 'Quorum was not met, so this result is not binding. :name led with :pct% of continuing ballots.',
        'how_counted' => 'How the count worked',
        'how_counted_hint' => 'Ranked choice counts in rounds. Each round counts every ballot’s top remaining choice; the option with the fewest votes is eliminated and its ballots move to their next choice, until one option has a majority.',
        'standing_note' => 'Shares are of the :continuing ballots counted in the final round.'
    ],
    'approval' => [
        'name' => 'Approval vote question',
        'description' => 'Voters approve of any number of options from a list.',
        'hint' => 'Choose one or more options.',
        'rate' => 'Approval rate (%)'
    ],
    'created' => [
        'success' => 'Component created.'
    ]
];
