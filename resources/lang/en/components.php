<?php

return [
    'option' => 'Option',
    'votes' => 'Votes',
    'oftotal' => 'Share(%)',
    'yesno' => [
        'yes' => 'Yes',
        'no' => 'No',
        'abstain' => 'Abstain',
        'name' => 'Yes/No question',
        'description' => 'Voters choose whether they support or oppose a single item.'
    ],
    'fptp' => [
        'name' => 'First past the post / Plurality question',
        'description' => 'Voters choose one item from a list of options.'
    ],
    'rankedchoice' => [
        'name' => 'Ranked choice question',
        'description' => 'Voters rank multiple options in order of their preference.',
        'intro' => 'There are :options options. Rank the options in order of your choice. You may rank as few or as many as you wish.',
        'state' => 'You have ranked {{ selected.length }}, you may rank {{ rankees.length - selected.length }} more.',
        'UP' => 'UP',
        'DOWN' => 'DOWN'
    ],
    'approval' => [
        'name' => 'Approval vote question',
        'description' => 'Voters approve of any number of options from a list.',
        'oftotal' => 'Approval(%)'
    ]
];
