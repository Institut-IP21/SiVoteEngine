<?php

return [
    'single' => 'Ballot',
    'preview' => [
        'warning' => 'This view will be available for 15 minutes. After that, you need a new link.'
    ],
    'result' => [
        'not_yet' => 'Ballot results not available yet',
        'title' => 'Ballot Results'
    ],
    'vote' => [
        'registered' => 'Your vote has been registered',
        'closeable' => 'You can close this page.',
        'failed' => [
            'title' => 'Vote not submitted',
            'heading' => 'Your vote could not be submitted',
            'intro' => 'Please review the questions below and try again.',
        ],
    ],
    'expired' => 'This ballot has already expired.',
    'voteId' => 'Vote ID',
    'quorum' => [
        'label' => 'Quorum',
        'met' => 'Quorum met',
        'status_failed' => 'Quorum not met',
        // The "result not binding" banner (D11): suppresses the winner verdict.
        'not_met' => 'Quorum not met — turnout :turnout of required :quorum; result not binding.',
        'status' => ':votes / :quorum votes',
    ],
];
