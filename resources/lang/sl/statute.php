<?php

/**
 * Bilingual statute/legal-reference clause text served by the statute API
 * (see local_docs/voting-components/statute-feature-spec.md and
 * statute-content-draft.md, the source this file transcribes). Each key
 * below is an ORDERED LIST of clause paragraphs; the numbering ("(1)",
 * "(2)", ...) is generated for display from array order and is never
 * stored here. `quorum` is the shared preamble, drafted once and shown
 * above every method's clauses (D11) — not duplicated into the 5 arrays.
 *
 * Static, generic reference text (D4) — no per-ballot value interpolation.
 *
 * NOTE: this Slovenian text is an original drafting pass, NOT yet reviewed
 * by a native Slovenian legal drafter — see statute-content-draft.md's
 * "Notes for the reviewer" §8. Two factual corrections were applied during
 * transcription (see AUTONOMOUS-BUILD-LOG.md, Wave 3 readiness notes):
 * yesno point (2) "tritretjinsko" -> "tričetrtinsko" (matches the actual
 * three_quarters preset), and orderedlist point (1) "oddal" -> "odda"
 * (present tense). Everything else is transcribed verbatim, flagged for
 * later native review, not rewritten here.
 */
return [
    'quorum' => [
        'Sklepčnost je posebna, izbirna zahteva, ki jo organizacija lahko določi za posamezno glasovanje. Če sklepčnost za glasovanje ni določena, izid tega glasovanja z udeležbo ni pogojen.',
        'Kadar je sklepčnost določena, se ugotavlja glede na udeležbo — število dejansko oddanih glasovnic v tem glasovanju — in ne glede na število vseh upravičencev do glasovanja. Oddana glasovnica se šteje v sklepčnost takoj ob oddaji, ne glede na to, ali se glasovi na njej pozneje izkažejo za veljavne po pravilih posamezne metode.',
        'Sklepčnost je dosežena, če je število oddanih glasovnic vsaj tolikšno, kolikor znaša sklepčno število, določeno za to glasovanje.',
        'Če sklepčnost ni dosežena, se štetje kljub temu izvede in v celoti zabeleži; odvzame se mu le zavezujoč učinek. Izid je v tem primeru le informativen — zaradi nedosežene sklepčnosti se štetje nikoli ne opusti, ne prekine in se izid ne prikrije.',
    ],

    'yesno' => [
        'Vsak upravičeni glasovalec glasuje o predlogu z glasom "za" ali "proti"; če glasovnica to omogoča, se lahko namesto tega glasovalec "vzdrži".',
        'Predlog je sprejet, če je med veljavno oddanimi glasovi za predlog število glasov "za" večje od števila glasov "proti" in če delež glasov "za" med njimi doseže prag, določen za to glasovanje. Prag je navadna večina — več kot polovica veljavno oddanih glasov "za" ali "proti" — razen če je organizacija za glasovanje določila kvalificiran prag (dvotretjinsko ali tričetrtinsko večino); prag za posamezno glasovanje nikoli ni nižji od navadne večine.',
        'Glas "vzdržan", kjer ga glasovnica omogoča, ter vsak prazen ali sicer neveljaven glas, se ne štejejo med glasove iz druge točke; o izidu odločajo izključno veljavno oddani glasovi "za" in "proti".',
        'Če je število glasov "za" enako številu glasov "proti", predlog ni sprejet, ne glede na veljavni prag. Tega izida ne spremeni odločilni glas predsedujočega niti žreb; enako število glasov je samo po sebi zadosten razlog, da predlog ni sprejet.',
        'Ali je to glasovanje vezano na sklepčnost in kakšne so posledice nedosežene sklepčnosti, ureja skupni člen o sklepčnosti, ki velja enotno za vsa glasovanja po tem statutu.',
    ],

    'fptp' => [
        'Vsak upravičeni glasovalec glasuje za eno od navedenih možnosti; če glasovnica to omogoča, se lahko namesto tega vzdrži.',
        'Izvoljena je možnost, ki je med veljavno oddanimi glasovi prejela največ glasov. Za izvolitev ni potreben kakšen najmanjši delež ali prag glasov; o izidu odloča izključno število prejetih glasov.',
        'Prazen glas se šteje za vzdržanega, če glasovnica to omogoča, sicer je neveljaven; glas za možnost, ki ni na glasovnici, ali glas, oddan v kateri koli drugi obliki kot z izbiro ene možnosti, je neveljaven. Vzdržani in neveljavni glasovi se ne štejejo v skupno število glasov posamezne možnosti iz druge točke.',
        'Če dve ali več možnosti prejmejo enako in hkrati najvišje število glasov med vsemi možnostmi, nastane med njimi izenačenje. Glasovalni sistem izenačenja ne razrešuje niti z odločilnim glasom niti z žrebom; izenačenje se razkrije, razreši pa se po pravilih organizacije (npr. z žrebom, s ponovnim glasovanjem med izenačenimi možnostmi ali z odločitvijo pristojnega organa).',
        'Ali je to glasovanje vezano na sklepčnost in kakšne so posledice nedosežene sklepčnosti, ureja skupni člen o sklepčnosti.',
    ],

    'rankedchoice' => [
        'Vsak upravičeni glasovalec za eno mesto glasuje tako, da kandidate razvrsti po vrstnem redu prednosti. Ni potrebno razvrstiti vseh kandidatov, dvema kandidatoma pa ni mogoče dodeliti enake prednosti. Glasovnica, na kateri glasovalec ni razvrstil nobenega kandidata, je prazna in se šteje za vzdržano, če to glasovnica omogoča, sicer je neveljavna.',
        'Štetje poteka po krogih. V vsakem krogu se vsaka še štejoča glasovnica prišteje kandidatu, ki je na njej najvišje uvrščen med kandidati, ki so še v postopku. Izvoljen je kandidat, ki mu je večina še štejočih glasovnic v tem krogu namenila to najvišjo preostalo prednost.',
        'Če noben kandidat ne doseže večine iz druge točke in je v postopku še več kot dva kandidata, se izloči kandidat z najmanj glasovi v tem krogu; vsaka glasovnica, ki je štela zanj, se prišteje kandidatu, ki je na njej naslednja veljavna preostala prednost. Glasovnica, na kateri ni več nobene veljavne preostale prednosti, je izčrpana in se v naslednjih krogih ne šteje več.',
        'Štetje iz druge in tretje točke se ponavlja, dokler kdo ne doseže večine iz druge točke ali dokler v postopku ne ostaneta dva kandidata. Če ostaneta dva, je izvoljen tisti z več glasovi v tem krogu; če imata enako število glasov, se uporabi šesta točka.',
        'Če je za izločitev iz tretje točke izenačenih več kandidatov z enakim, najnižjim številom glasov, se izloči tisti od njih, ki je imel manj glasov v najbližjem prejšnjem krogu, v katerem se je število glasov izenačenih kandidatov razlikovalo. To pravilo glasovalni sistem uporablja samodejno in dosledno; žreba ne vključuje.',
        'Če ob izidu, ki bi sicer sledil iz druge ali četrte točke, med kandidati ostane izenačenje — bodisi izenačenje za izvolitev med zadnjima dvema kandidatoma, bodisi izenačenje, ki ga peta točka ne more razrešiti, ker je bilo število glasov izenačenih kandidatov enako v vseh prejšnjih krogih — ni izvoljen nihče. Izenačenje se razkrije med zadevnimi kandidati in se razreši po pravilih organizacije.',
        'Prazna glasovnica in glasovnica, ki nima več nobene veljavne preostale prednosti med kandidati v postopku, se v nobenem krogu ne štejeta; večina iz druge točke se ugotavlja glede na glasovnice, ki v tem krogu še štejejo, ne glede na skupno število oddanih glasovnic.',
        'Ali je to glasovanje vezano na sklepčnost in kakšne so posledice nedosežene sklepčnosti, ureja skupni člen o sklepčnosti.',
    ],

    'approval' => [
        'Vsak upravičeni glasovalec lahko odobri poljubno število navedenih možnosti, od nič do vseh; glasovalec ni omejen na eno izbiro, odobritev ene možnosti pa ne izključuje odobritve katere koli druge.',
        'Izvoljene so možnosti z največjim številom odobritev med veljavno oddanimi glasovi, in sicer do števila mest, določenega za to glasovanje (privzeto eno mesto). Če je na glasovnici manj možnosti, kot je določenih mest, so izvoljene vse navedene možnosti. Za izvolitev ni potreben kakšen najmanjši delež ali prag odobritev; o zasedbi vsakega mesta odloča izključno relativna uvrstitev po številu prejetih odobritev.',
        'Glasovalec, ki na glasovnici ne odobri nobene možnosti, se šteje za vzdržanega, če glasovnica to omogoča, sicer je njegov glas neveljaven; odobritev možnosti, ki ni na glasovnici, ali odobritev, oddana v drugi obliki, je neveljavna in se ne šteje v skupno število odobritev te možnosti iz druge točke.',
        'Če je na meji med zadnjim zasedenim mestom in prvo nezasedeno možnostjo število odobritev dveh ali več možnosti enako — tako da iz samih odobritev ni mogoče ugotoviti, katera od njih zasede preostalo mesto (preostala mesta) — se izenačenje med njimi razkrije. Glasovalni sistem ga ne razrešuje niti z odločilnim glasom niti z žrebom; razreši se po pravilih organizacije (npr. z žrebom, s ponovnim glasovanjem med izenačenimi možnostmi ali z odločitvijo pristojnega organa).',
        'Ali je to glasovanje vezano na sklepčnost in kakšne so posledice nedosežene sklepčnosti, ureja skupni člen o sklepčnosti.',
    ],

    'orderedlist' => [
        'Vsak upravičeni glasovalec odda eno glasovnico, na kateri od navedenih možnosti izbere podmnožico, ki jo odobrava, in to podmnožico razvrsti po svoji prednosti. Možnost, ki je glasovalec ni navedel, s tem ni odobrena. Glasovnica, na kateri glasovalec ne odobri nobene možnosti, je prazna in se šteje za vzdržano, če to glasovnica omogoča, sicer je neveljavna.',
        'Za namene štetja se šteje, da glasovnica med dvema možnostma A in B daje prednost tisti, ki jo je glasovalec odobril, če druge ni odobril, oziroma — če je odobril obe — tisti, ki jo je uvrstil pred drugo. Glasovnica, ki ne odobri niti A niti B, med njima ne izraža prednosti.',
        'Možnosti se med seboj primerjajo parno: za vsak par možnosti A in B se število glasovnic, ki dajejo prednost A pred B (kot v drugi točki), primerja s številom tistih, ki dajejo prednost B pred A. Če je prvo število večje, ima A pred B odločilno prednost v višini te razlike; če sta števili enaki, na podlagi te primerjave nobena od možnosti nima odločilne prednosti pred drugo.',
        'Možnost A je v izidu uvrščena pred možnostjo B, če je najmočnejša veriga odločilnih prednosti, ki vodi od A do B — prek poljubnega števila vmesnih možnosti, vštevši, kot najpreprostejši primer, neposredno odločilno prednost med A in B brez vmesnih možnosti, pri čemer je vsak člen verige odločilna prednost iz tretje točke — močnejša (torej ima na svojem najšibkejšem členu večjo razliko) od najmočnejše take verige, ki vodi od B do A. Če nobena smer nima verige, močnejše od nasprotne — vštevši primer, ko sploh ni verige v nobeni smeri — sta A in B resnično izenačena in nobena ni uvrščena pred drugo.',
        'Možnosti se razvrstijo od najbolj do najmanj zaželene glede na četrto točko; izvoljene so možnosti, uvrščene znotraj števila mest, določenega za to glasovanje, po tem vrstnem redu (privzeto je število mest enako številu možnosti na glasovnici, tako da so izvoljene in razvrščene vse možnosti).',
        'Kadar četrta točka dveh ali več možnosti ne postavi v strogo medsebojno razmerje — ker so resnično izenačene ali ker jih povezuje veriga izenačenj — te možnosti tvorijo skupino, ki si deli mesta med seboj. Kadar takšna skupina sega čez zadnje mesto, ki se še zaseda, iz glasov samih ni mogoče ugotoviti, katera od možnosti skupine zasede preostalo mesto (preostala mesta), zato se to izenačenje razkrije. Glasovalni sistem ga ne razrešuje niti z odločilnim glasom niti z žrebom; razreši se po pravilih organizacije (npr. z žrebom, s ponovnim glasovanjem med izenačenimi možnostmi ali z odločitvijo pristojnega organa). Enako velja za izenačenje, ki vpliva le na medsebojni vrstni red že izvoljenih možnosti.',
        'Organizacija lahko za posamezno glasovanje določi kvoto sestave: najmanjše ali največje število mest izmed možnosti, izvoljenih po točkah 4–6, ki morajo biti zasedena z možnostmi iz določene kategorije (npr. najmanjše število mest, zasedenih z možnostmi določene skupine). Kadar je kvota sestave določena in je izvoljene možnosti sama ne bi izpolnile, se izvoljene možnosti prilagodijo tako, da se zamenja najmanjše potrebno število možnosti — tiste najbliže meji zasedbe — z možnostmi iz zahtevane kategorije, ob čim večjem ohranjanju vrstnega reda iz četrte točke. Kvota sestave je pravilo o kategoriji, ki ji pripada posamezna izvoljena možnost; ne dodeljuje, ne prerazporeja in ne preračunava glasov, zato je ni mogoče enačiti z nobeno na glasovih temelječo kvoto.',
        'Kadar bi izpolnitev kvote sestave iz sedme točke terjala zamenjavo možnosti, katerih medsebojni vrstni red je sam izenačen po šesti točki, ali kadar so mesta, na katera vpliva kvota, del še nerazrešenega izenačenja na meji zasedbe, se prilagoditev ne izvede; namesto tega se tudi kvota sestave razkrije kot nerazrešena do razrešitve izenačenja po šesti točki. Kadar je organizacija določila, da je kvota sestave le informativna in ne zavezujoča, se izvoljene možnosti zaradi nje sploh ne prilagodijo; kvota takrat služi le kot ugotovitev ob dejanskem izidu po točkah 4–6.',
        'Ali je to glasovanje vezano na sklepčnost in kakšne so posledice nedosežene sklepčnosti, ureja skupni člen o sklepčnosti.',
    ],
];
