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
        'counter' => 'Ranked: :selected of :total',
        'remaining' => 'Remaining options',
        'add' => 'Add :name to your ranking',
        'add_short' => 'Add',
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
        'winner_is' => 'The winner is',
        'no_winner' => 'There is no conclussive winner, the possible outcomes are',
        'continuing' => 'Continuing ballots',
        'exhausted' => 'Exhausted ballots'
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
