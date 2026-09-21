<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Filter toggle
    |--------------------------------------------------------------------------
    |
    | Turn the whole filter off without editing code. Everything still goes
    | through admin moderation on the wall, so this only controls the automatic
    | rejection that happens before a post or chat message is stored.
    |
    */

    'enabled' => env('PROFANITY_FILTER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Blocked terms
    |--------------------------------------------------------------------------
    |
    | Write terms in plain lowercase letters. ContentFilterService handles the
    | evasions on its own, so there is no need to list them here:
    |
    |   - casing and accents      PuTa, pÚta
    |   - leet swaps              p0ta, sh1t, @sshole, fvck
    |   - stretched letters       puuutaaa, fuuuck
    |   - masked letters          p*ta, f**k
    |   - spaced out letters      p u t a, f.u.c.k
    |   - common endings          gago -> gagong, fuck -> fucking, bitch -> bitches
    |
    | A trailing "*" means "and anything that follows", for terms that only ever
    | start abusive words (tangina -> tanginamo). Leave it off for short terms
    | that live inside innocent words, or "ass" would flag "assignment".
    |
    | A term may contain spaces ("hayop ka"); any punctuation matches the gap.
    |
    */

    'blocked' => [

        // Filipino — the bulk of the trash talk this wall actually gets.
        'putangina*', 'putang ina*', 'potangina*', 'potang ina*', 'puta ka',
        'tangina*', 'tang ina*', 'kingina*', 'kinangina*', 'amputa', 'ampota',
        'puta', 'pota', 'putcha', 'pucha', 'putragis',
        'gago', 'gaga', 'gagu', 'gunggong', 'ulol', 'ulul', 'engot',
        'bobo', 'tanga', 'inutil', 'siraulo', 'sira ulo', 'tarantad*',
        'kupal', 'hinayupak', 'hayop ka', 'hayup ka', 'punyeta', 'punyeta ka',
        'pakshet', 'pakshit', 'pakyu', 'pakingshet', 'pakingsyet',
        'letse', 'lintik', 'bwisit', 'buwisit', 'bwiset', 'ulupong',
        'walang hiya', 'walanghiya*',

        // Bisaya / Visayan.
        'yawa', 'piste', 'pisti', 'buang', 'bilat', 'puday', 'atay ka',

        // Filipino — sexual.
        'kantot*', 'kantut*', 'jakol', 'jakul', 'salsal', 'chupa', 'tsupa',
        'burat', 'bayag', 'tite', 'titi', 'otin', 'puki', 'pekpek', 'pepe',
        'libog', 'malibog', 'tamod', 'iyot', 'pokpok', 'malandi',

        // English.
        'fuck*', 'fuk', 'motherfuck*', 'mother fuck*', 'bullshit*', 'shit*',
        'bitch*', 'asshole*', 'dumbass*', 'jackass*', 'ass', 'bastard*',
        'cunt*', 'dick', 'dickhead*', 'cock', 'cocksucker*', 'pussy',
        'pussies', 'whore*', 'slut*', 'prick', 'tits', 'titt*', 'boob*',
        'cum', 'anal', 'blowjob', 'handjob', 'porn*',
        'rape', 'raped', 'rapist*', 'kys', 'kill yourself', 'kill urself',

        // Slurs. These are never a judgement call — always reject.
        'nigger*', 'nigga*', 'faggot*', 'fag', 'retard*', 'kike', 'chink',
        'spic', 'tranny', 'gook', 'wetback', 'coon',

    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed phrases
    |--------------------------------------------------------------------------
    |
    | Innocent words and phrases that a term above would otherwise catch. They
    | are lifted out of the text before it is scanned, so "Lady Gaga" passes
    | while a bare "gaga" still does not. Add to this list whenever a real post
    | gets rejected for the wrong reason — it is the escape hatch.
    |
    */

    'allowed' => [
        'lady gaga',   // "gaga"
        'titis',       // "titi"
        'maine coon',  // "coon"
        'retardant',   // "retard*"
    ],

];
