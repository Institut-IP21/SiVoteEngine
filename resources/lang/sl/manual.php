<?php

// TODO(sl-review): machine-drafted Slovenian, needs native review

/**
 * Glej resources/lang/en/manual.php za opis namena in vira te vsebine.
 * Ohranjajte skladnost ključev z en/manual.php.
 */
return [
    'yesno' => [
        'Razvrsti vsako oddano glasovnico v Da, Ne, Abstinenca ali neveljavno; k izidu štejeta le glasovnici Da in Ne.',
        'Seštej glasovnice Da in glasovnice Ne.',
        'Če sta oba seštevka enaka, predlog ne uspe brez izjeme — enakega izida ne razreši niti glas predsedujočega niti žreb.',
        'V nasprotnem primeru izračunaj delež Da: število Da deli z vsoto Da in Ne, ter ga primerjaj s pragom, določenim za glasovanje — navadna večina (nad polovico) je privzeta, sicer pa dve tretjini, tri četrtine ali poljuben odstotek, če ga je organizacija tako določila.',
        'Predlog je sprejet le, če je Da pred Ne IN delež Da doseže ta prag.',
        'Ločeno, če je organizacija določila kvorum, preveri skupno število oddanih glasovnic (ne le Da in Ne) glede na kvorum: pod kvorumom se enak seštevek še vedno v celoti izvede in prikaže, vendar je le informativen — ni zavezujoč.',
    ],

    'fptp' => [
        'Vsako oddano glasovnico razvrsti k možnosti, ki jo označuje; prazna glasovnica, neznan odgovor ali (kjer je abstinenca dovoljena) žeton za abstinenco se izloči.',
        'Seštej veljavne glasove za vsako navedeno možnost — vsaka možnost začne štetje pri nič, tudi tiste, za katere ni glasoval nihče.',
        'Zmaga možnost z največ glasovi. Ni potrebnega najmanjšega deleža ali praga — zadostuje že navadna relativna večina.',
        'Če sta dve ali več možnosti izenačeni z največ glasovi, je izid neodločen: sistem to samo poroča in ne odloča — to je prepuščeno pravilom organizacije.',
    ],

    'approval' => [
        'Razvrsti odobritve vsake glasovnice: vsaka možnost, ki jo je volivec označil, prejme eno odobritev; glasovnica, ki ne odobri ničesar, je abstinenca (kjer je dovoljena) ali neveljavna.',
        'Seštej odobritve, ki jih je prejela vsaka možnost — vsaka navedena možnost začne pri nič.',
        'Možnosti razvrsti od največ do najmanj odobritev.',
        'Preberi prvih K možnosti na tej lestvici, kjer je K število mest, določeno za glasovanje (privzeto eno) — te možnosti so izvoljene.',
        'Če sta dve ali več možnosti izenačeni natanko na meji zadnjega mesta in bi zasedba vseh teh možnosti zahtevala več mest, kot jih je še na voljo, je to mesto sporočeno kot sporno in ni odločeno s strani sistema — razrešijo ga pravila organizacije.',
    ],

    'rankedchoice' => [
        'Izloči prazne glasovnice (nič razvrščenega) in neveljavne glasovnice (razvrščene so bile le možnosti, ki niso na tej glasovnici); vsaka druga glasovnica se šteje naprej, krog za krogom, dokler ni izčrpana.',
        'V vsakem krogu se vsaka še štejoča glasovnica šteje za tisto preostalo možnost, ki je na njej najvišje uvrščena med še sodelujočimi.',
        'Če ima ena možnost zdaj več kot polovico še štejočih glasovnic, zmaga brez nadaljevanja.',
        'Če ostaneta natanko dve možnosti in nobena ne doseže te večine, zmaga tista z več glasovi v tem krogu; enak razrez med zadnjima dvema se sporoči kot neodločen izid.',
        'V nasprotnem primeru se izloči možnost(i) z najmanj glasovi v krogu — vse možnosti, izenačene pri nič glasovih, se izločijo skupaj hkrati — in vsaka njihova glasovnica se premakne na naslednjo še sodelujočo prednost. Glasovnica brez nadaljnje razvrščene, še sodelujoče prednosti postane izčrpana in v poznejših krogih ne šteje več.',
        'Kadar sta namesto tega dve ali več možnosti (ne pri nič) izenačeni za zadnje mesto, se pogleda nazaj do zadnjega prejšnjega kroga, v katerem so se njihovi glasovi dejansko razlikovali, in izloči tista, ki je imela tam manj glasov; če so bile izenačene v vsakem prejšnjem krogu, se neodločen izid sporoči, namesto da bi ga razrešil sistem.',
        'Postopek od drugega koraka se ponavlja, dokler ni razglašen zmagovalec. Revizijska sled za vsak krog zapiše, zakaj se je končal tako, kot se je (dosežena večina, izločitev zadnjega mesta, razrešitev s pogledom nazaj itd.), poleg tega pa še, koliko glasovnic je bilo oddanih, praznih, neveljavnih ali izčrpanih na poti.',
    ],

    'orderedlist' => [
        'Za vsako glasovnico in vsak par kandidatov A in B ugotovi, katerega ima raje: če je volivec odobril A, ne pa B (ali je A uvrstil pred B), glasovnica daje prednost A; kandidat, ki ga volivec ni odobril sploh, nima prednosti pred drugim kandidatom, ki ga tudi ni odobril.',
        'Za vsak par primerjaj, koliko glasovnic ima raje A pred B, s tem, koliko jih ima raje B pred A. Stran z več glasovnicami ima "odločilno" prednost pred drugo, v višini te razlike; natančno izenačeno število ni odločilno v nobeno smer.',
        'Za vsak par kandidatov poišči najmočnejšo verigo odločilnih prednosti, ki ju povezuje — po možnosti tudi prek drugih kandidatov kot vmesnih členov ("pot zmage"). Kandidat prekaša drugega, če je njegova najmočnejša veriga proti njemu močnejša kot veriga v nasprotni smeri.',
        'Kandidate uredi od tistega, ki najbolj prekaša druge, do tistega, ki jih najmanj prekaša. Kandidat, ki v neposrednem soočenju prekaša vsakega drugega kandidata — Condorcetov zmagovalec — je vedno na prvem mestu, kadar tak kandidat obstaja.',
        'Kandidati na zgornjih mestih (število mest, določeno za glasovanje — privzeto vsi) so izvoljeni, v tem vrstnem redu. Kjer razvrstitev ne more strogo določiti vrstnega reda dveh ali več kandidatov med seboj, si ti delijo izenačeno skupino; če ta skupina sega čez zadnje mesto, je meja sporočena kot sporna in ni odločena s strani sistema.',
        'Če je organizacija določila kvoto po kategorijah (najmanjše ali največje število za določeno označeno skupino, ali zahtevo po izmenjevanju "na zadrgo") in jo označila kot zavezujočo, se ta uporabi naknadno z zamenjavo najmanjšega potrebnega števila že zasedenih mest, da se zahteva izpolni. Kvota, označena kot le informativna, se še vedno izračuna in poroča ob rezultatu, vendar nikoli ne spremeni dejansko izvoljenih.',
    ],
];
