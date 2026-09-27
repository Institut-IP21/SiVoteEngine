<?php

// TODO(sl-review): machine-drafted Slovenian, needs native review

/**
 * Glej resources/lang/en/academic.php za opis namena in vira te vsebine
 * (preseljeno iz web_app, da je akademska razlaga vsake metode glasovanja
 * zbrana na enem mestu — v engine). Ohranjajte skladnost ključev z
 * en/academic.php.
 */
return [
    'yesno' => [
        'explanation' => 'Glasovanje da/ne od članov zahteva, da sprejmejo ali zavrnejo en sam predlog, izid pa je odvisen od tega, ali delež glasov Da doseže zahtevani prag (navadna večina, razen če je organizacija določila višjega). Gre za najbolj neposredno obliko skupinskega odločanja: eno vprašanje, dva možna odgovora.',
        'pros' => [
            'Preprosto za razumevanje, glasovanje in preštevanje — ni razvrščanja ali zasnove glasovnice, pri kateri bi bilo mogoče kaj zgrešiti.',
            'Daje jasen in odločen mandat: predlog je sprejet ali ni.',
            'Ohranja pozornost na enem vprašanju hkrati, zato ni mogoče pomešati nepovezanih tem.',
        ],
        'cons' => [
            'Nianse in mešana mnenja strne v eno samo binarno izbiro, brez možnosti izraziti, kako močno nekdo čuti.',
            'Izid je močno odvisen od tega, kako je vprašanje ubesedil in oblikoval predlagatelj.',
            'Če predlog ne uspe, ne pove ničesar o drugih najljubših izbirah volivcev.',
        ],
    ],

    'fptp' => [
        'explanation' => "Pri metodi relativne večine vsak volivec označi enega samega favorita med možnostmi; zmaga tista, ki zbere največ glasov, tudi brez absolutne večine. Gre za klasično glasovnico 'izberi enega', ki se uporablja pri volitvah z enim zmagovalcem.",
        'pros' => [
            'Zelo preprosto za glasovanje (označiš eno polje) in za preštevanje.',
            'Vedno prinese enega, odločnega zmagovalca brez nadaljnjih krogov.',
            'Volivcem je znana, kar krepi zaupanje v izid.',
        ],
        'cons' => [
            'Zmagovalec lahko dobi mesto z precej manj kot polovico glasov, kadar si več podobnih možnosti razdeli glasove.',
            'Lahko volivce spodbudi k "taktičnemu" glasovanju za uresničljivo možnost namesto za resničnega favorita.',
            'Ne zajame ničesar o tem, kako bi volivci razvrstili druge možnosti.',
        ],
    ],

    'rankedchoice' => [
        'explanation' => 'Razvrstitveno glasovanje (takojšnji drugi krog) od volivcev zahteva, da možnosti razvrstijo po prednosti. Če nobena možnost nima večine prvih izbir, se možnost z najmanj glasovi izloči, njeni glasovi pa se prenesejo na naslednjo izbiro na posamezni glasovnici; postopek se ponavlja, dokler ena možnost ne doseže večine.',
        'pros' => [
            'Zagotavlja, da ima zmagovalec podporo večine med preostalimi možnostmi, ne le relativne večine.',
            'Volivci lahko na prvo mesto uvrstijo manj priljubljenega favorita, ne da bi "zapravili" glas, saj se ta lahko prenese, če je možnost izločena.',
            'Omili učinek "kvarjenja glasov", ki pesti glasovanje z eno samo označbo.',
        ],
        'cons' => [
            'Postopek štetja je težje slediti kot preprosto seštevanje — to je resnični kompromis na račun preglednosti.',
            'Prenosi glasov se lahko v redkih primerih obnašajo protiintuitivno: višja uvrstitev kandidata lahko izjemoma prispeva k njegovemu izpadu.',
            'Splošno sprejemljive zmerne možnosti lahko zaradi premalo prvih izbir izpadejo prezgodaj ("stiskanje sredine").',
        ],
    ],

    'approval' => [
        'explanation' => 'Odobritveno glasovanje vsakemu volivcu omogoča, da označi vse možnosti, ki se mu zdijo sprejemljive, ne le eno; mesta zasedejo možnosti z največ odobritvami. Metoda je primerna za izbiro več zmagovalcev hkrati (odbor, ožji seznam) iz skupnega nabora kandidatov.',
        'pros' => [
            'Volivci lahko hkrati podprejo več sprejemljivih možnosti, brez razvrščanja ali izključevanja preostalih.',
            'Zmanjša učinek "kvarjenja glasov", značilen za relativno večino, saj odobritev podobne možnosti nikoli ne stane glasu priljubljene.',
            'Glasovnica ostaja preprosta — označiš, kolikor želiš.',
        ],
        'cons' => [
            'Ne zajame, kako močno volivec eno odobreno možnost raje vidi od druge.',
            'Izid je občutljiv na to, kje vsak volivec sam potegne mejo med "sprejemljivo" in "ne" — kar je že samo po sebi strateška odločitev.',
            'Splošno sprejemljiva možnost lahko prekosi tisto, ki jo manjša skupina podpira veliko močneje.',
        ],
    ],

    'orderedlist' => [
        'explanation' => 'Metoda urejenega seznama razvrsti kandidate tako, da na vseh glasovnicah primerja vsak par kandidatov med seboj, kar namesto enega zmagovalca ustvari celoten urejen seznam — uporabno za zasedbo več mest po vrstnem redu prednosti. (SiVotova izvedba sledi Schulzejevi metodi/metodi beatpath.)',
        'pros' => [
            'Izvoli možnost, ki bi premagala vsako drugo možnost v neposrednem soočenju, kadar takšna možnost obstaja — močno zagotovilo pravičnosti.',
            'Bolj odporna kot enostavnejše metode na to, da bi volivec pridobil prednost z napačnim prikazovanjem svojih resničnih preferenc.',
            'Ustvari popoln urejen izid, kar je primerno za zasedbo več mest po prednostnem vrstnem redu, ne le za izbiro enega zmagovalca.',
        ],
        'cons' => [
            'Primerjava parov, na kateri temelji izid, je matematično zahtevna in jo je težko preveriti na roko.',
            'Krožne preference med volivci (nihče ne premaga vseh) lahko za zadnje mesto še vedno zahtevajo pravilo za razrešitev neodločenega izida.',
            'Zahteva, da volivci razvrstijo vse kandidate, kar je zahtevnejše kot ena sama označba ali seznam odobritev.',
        ],
    ],
];
