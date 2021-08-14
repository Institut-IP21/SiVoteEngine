<?php

return [
    'option' => 'Vrednost',
    'votes' => 'Število glasov',
    'winner' => 'Zmagovalec',
    'eliminated' => 'Eliminiran',
    'tie' => 'Izenačen',
    'oftotal' => 'Delež (%)',
    'yesno' => [
        'yes' => 'Da',
        'no' => 'Ne',
        'abstain' => 'Vzdržan',
        'name' => 'Da/Ne vprašanje',
        'tie' => 'Izid je neodločen.',
        'description' => 'Volivci izrazijo bodisi podporo bodisi nasprotovanje dani postavki.'
    ],
    'fptp' => [
        'name' => 'First past the post / Plurality vprašanje',
        'abstain' => 'Vzdržan',
        'tie' => 'Izid je neodločen. Isto število glasov so prejeli: ',
        'description' => 'Volivci izberejo natančno eno možnost iz seznama.'
    ],
    'rankedchoice' => [
        'name' => 'Preferenčno vprašanje',
        'description' => 'Volivci seznam možnosti uredijo po vrsti glede na preferenco.',
        'intro' => 'Pred vami je :options opcij. Razvrstite jih po vrsti od najboljše do najslabše. Razvrstite lahko kolikor opcij želite.',
        'state' => 'Do sedaj ste razvrstili {{ selected.length }} opcij, lahko jih še {{ rankees.length - selected.length }}.',
        'UP' => 'GOR',
        'DOWN' => 'DOL',
        'round' => 'Runda',
        'winner_is' => 'Zmagovalec je',
        'no_winner' => 'Zmagovalca ni mogoče določiti, možni zmagovalci so',
        'tie_elimination' => 'Izenačeno - eliminiran'
    ],
    'approval' => [
        'name' => 'Approval vote',
        'description' => 'Volivci označijo, katere izmed možnosti na seznamu podpirajo.',
        'oftotal' => 'Podpora (%)'
    ],
    'created' => [
        'success' => 'Component created.'
    ]
];
