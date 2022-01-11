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
        'description' => 'Glasovalci izbirajo med Da, Ne, in glede na nastavitve, Vzdržano.'
    ],
    'fptp' => [
        'name' => 'Izbira ene vrednosti izmed večih',
        'abstain' => 'Vzdržan',
        'tie' => 'Izid je neodločen. Isto število glasov so prejeli: ',
        'description' => 'Glasovalci izberejo natančno eno izmed izbir.'
    ],
    'rankedchoice' => [
        'name' => 'Razvrščanje vrednosti',
        'description' => 'Glasovalci seznam možnosti uredijo po vrsti glede na preferenco.',
        'intro' => 'Pred vami je :options opcij. Razvrstite jih po vrsti od najboljše do najslabše. Razvrstite lahko kolikor opcij želite.',
        'state' => 'Do sedaj ste razvrstili :selected opcij, lahko jih še :remaining.',
        'UP' => 'GOR',
        'DOWN' => 'DOL',
        'round' => 'Runda',
        'winner_is' => 'Zmagovalec je',
        'no_winner' => 'Zmagovalca ni mogoče določiti, možni zmagovalci so',
        'tie_elimination' => 'Izenačeno - eliminiran'
    ],
    'approval' => [
        'name' => 'Odobritveni glas',
        'description' => 'Glasovalci označijo, katere izmed možnosti na seznamu podpirajo.',
        'oftotal' => 'Podpora (%)'
    ],
    'created' => [
        'success' => 'Component created.'
    ]
];
