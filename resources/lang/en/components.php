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
        'invalid' => 'Invalid',
        'tie' => 'The outcome is a tie. The following options received the same number of votes: ',
        'description' => 'Voters choose one item from a list of options.'
    ],
    'rankedchoice' => [
        'name' => 'Ranked choice question',
        'description' => 'Voters rank multiple options in order of their preference.',
        'intro' => 'There are :options options. Rank the options in order of your choice. You may rank as few or as many as you wish.',
        'state' => 'You have ranked :selected, you may rank :remaining more.',
        'UP' => 'UP',
        'DOWN' => 'DOWN',
        'round' => 'Round',
        'winner_is' => 'The winner is',
        'no_winner' => 'There is no conclussive winner, the possible outcomes are',
        'continuing' => 'Continuing ballots',
        'exhausted' => 'Exhausted ballots'
    ],
    'approval' => [
        'name' => 'Approval vote question',
        'description' => 'Voters approve of any number of options from a list.',
        'rate' => 'Approval rate (%)'
    ],
    'created' => [
        'success' => 'Component created.'
    ]
];
