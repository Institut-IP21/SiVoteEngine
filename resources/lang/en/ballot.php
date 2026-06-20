<?php

return [
    'single' => 'Ballot',
    'submit' => 'Cast vote',
    'anonymous' => 'Your vote will be recorded anonymously.',
    'powered_by' => 'Voting runs on the open-source SiVote system and the eGlasovanje.si platform.',
    'no_questions' => 'This ballot has no questions yet.',
    'preview' => [
        'warning' => 'This view will be available for 15 minutes. After that, you need a new link.',
        'sample_question' => 'Sample question',
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
    'session' => [
        'no_open_questions' => 'No questions are open right now. This page updates automatically when the next one opens.',
    ],
    'voteId' => 'Vote ID',
    'code_info' => 'The code you received (also part of the link that brought you here) is how the system knows you are eligible to vote without knowing who you are. Keep it to yourself and do not share it. After voting, you can use it to check that your votes were recorded correctly.',
    'quorum' => [
        'met' => 'Quorum met',
        // The "result not binding" message (D11): suppresses the winner verdict.
        'not_met' => 'Quorum not met — turnout :turnout of required :quorum; result not binding.',
        'status' => ':votes / :quorum votes',
    ],
];
