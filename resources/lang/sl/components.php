<?php

return [
    'option' => 'Vrednost',
    'votes' => 'Število glasov',
    'oftotal' => 'Delež(%)',
    'yesno' => [
        'yes' => 'Da',
        'no' => 'Ne',
        'abstain' => 'Vzdržan',
        'name' => 'Da/Ne vprašanje',
        'description' => 'Volivci izrazijo bodisi podporo bodisi nasprotovanje dani postavki.'
    ],
    'fptp' => [
        'name' => 'First past the post / Plurality vprašanje',
        'description' => 'Volivci izberejo natančno eno možnost iz seznama.'
    ],
    'rankedchoice' => [
        'name' => 'Preferenčno vprašanje',
        'description' => 'Volivci seznam možnosti uredijo po vrsti glede na preferenco.',
        'intro' => 'Pred vami je :options opcij. Razvrstite jih po vrsti od najboljše do najslabše. Razvrstite lahko kolikor opcij želite.',
        'state' => 'Do sedaj ste razvrstili {{ selected.length }} opcij, lahko jih še {{ rankees.length - selected.length }}.',
        'UP' => 'GOR',
        'DOWN' => 'DOL'
    ],
    'approval' => [
        'name' => 'Approval vote',
        'description' => 'Volivci označijo, katere izmed možnosti na seznamu podpirajo.',
        'oftotal' => 'Podpora(%)'
    ],
    'created' => [
        'success' => 'Component created.'
    ]
];
