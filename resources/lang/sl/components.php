<?php

return [
    'option' => 'Izbira',
    'votes' => 'Število glasov',
    'winner' => 'Zmagovalec',
    'eliminated' => 'Eliminiran',
    'tie' => 'Izenačen',
    'oftotal' => 'Delež (%)',
    'share_valid' => 'Delež veljavnih glasov (%)',
    'yesno' => [
        'yes' => 'Da',
        'no' => 'Ne',
        'abstain' => 'Vzdržan',
        'hint' => 'Izberite eno možnost.',
        'name' => 'Da/Ne vprašanje',
        'tie' => 'Izid je neodločen.',
        'description' => 'Glasovalci izbirajo med Da, Ne, in glede na nastavitve, Vzdržano.',
        'invalid' => 'Neveljavno',
        'carried' => 'Predlog sprejet',
        'not_carried' => 'Predlog ni sprejet',
        'not_carried_tied' => 'Predlog ni sprejet (neodločeno :yes–:no)'
    ],
    'fptp' => [
        'name' => 'Izbira ene vrednosti izmed večih',
        'abstain' => 'Vzdržan',
        'hint' => 'Izberite enega kandidata.',
        'invalid' => 'Neveljavno',
        'tie' => 'Izid je neodločen. Isto število glasov so prejeli: ',
        'description' => 'Glasovalci izberejo natančno eno izmed izbir.'
    ],
    'rankedchoice' => [
        'name' => 'Razvrščanje vrednosti',
        'description' => 'Glasovalci seznam možnosti uredijo po vrsti glede na preferenco.',
        'hint' => 'Pritisnite po vrsti — 1 = najljubši. Razvrstite poljubno število; ni treba razvrstiti vseh.',
        'counter' => 'Razvrščeni: :selected od :total',
        'remaining' => 'Preostale možnosti',
        'add' => 'Dodaj :name na seznam',
        'add_short' => 'Dodaj',
        'preview_static' => 'Razvrščanje s povleci-in-spusti je interaktivno na živi glasovnici.',
        'move_up' => 'Premakni :name navzgor',
        'move_down' => 'Premakni :name navzdol',
        'move_top' => 'Premakni :name na vrh',
        'remove' => 'Odstrani :name s seznama',
        'position' => ':name, izbira :rank od :total',
        'announce_added' => ':name dodan kot izbira :rank',
        'announce_removed' => ':name odstranjen iz razvrstitve',
        'announce_moved' => ':name, zdaj izbira :rank',
        'drag' => 'Povleci za razvrstitev',
        'requires_js' => 'Za razvrščanje tega vprašanja je potreben JavaScript. Prosimo, omogočite ga in osvežite stran.',
        'round' => 'Krog',
        'winner_is' => 'Zmagovalec je',
        'no_winner' => 'Zmagovalca ni mogoče določiti, možni zmagovalci so',
        'continuing' => 'Glasovnice v igri',
        'exhausted' => 'Izčrpane glasovnice'
    ],
    'approval' => [
        'name' => 'Odobritveni glas',
        'description' => 'Glasovalci označijo, katere izmed možnosti na seznamu podpirajo.',
        'hint' => 'Izberite eno ali več možnosti.',
        'rate' => 'Stopnja podpore (%)'
    ],
    'created' => [
        'success' => 'Komponenta ustvarjena.'
    ]
];
