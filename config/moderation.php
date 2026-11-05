<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Risk assessment toggle
    |--------------------------------------------------------------------------
    |
    | Turn the risk assessor off without editing code. Nothing here ever
    | rejects a submission — a flagged post still lands in the same pending
    | queue a clean one does, it just sorts to the top of it. Switching this
    | off only means the queue goes back to oldest-first.
    |
    */

    'enabled' => env('CONTENT_RISK_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Category weights
    |--------------------------------------------------------------------------
    |
    | What each signal adds to a submission's risk score. Scores are summed and
    | capped at 100, so a post that both doxxes someone and names them lands
    | well clear of one that only does the latter.
    |
    | These are triage weights, not verdicts. "contact_details" sits highest
    | because it is the one category that is almost never a false positive and
    | is a Facebook policy violation on its own (Privacy Violations), whoever
    | the post is about.
    |
    */

    'weights' => [
        'self_harm' => 70,
        'contact_details' => 60,
        'named_target' => 45,
    ],

    /*
    |--------------------------------------------------------------------------
    | Level thresholds
    |--------------------------------------------------------------------------
    |
    | The score at which a submission starts being called "low", "medium" and
    | "high" in the dashboard. Anything below the "low" mark reads as "none".
    |
    */

    'levels' => [
        'low' => 1,
        'medium' => 40,
        'high' => 70,
    ],

    /*
    |--------------------------------------------------------------------------
    | Course codes
    |--------------------------------------------------------------------------
    |
    | Programme prefixes used at BSU. A post that says "BSIT 2-A" has narrowed
    | itself to about forty people, which is close enough to naming someone for
    | the queue to want it read early. Written without the year-and-section part
    | — the assessor matches that itself.
    |
    */

    'course_codes' => [
        'bsit', 'bscs', 'bsis', 'bsemc', 'bsba', 'bsa', 'bsais', 'bscrim',
        'bsed', 'beed', 'bsn', 'bsmt', 'bspsych', 'bshm', 'bstm', 'abcom',
        'absoc', 'bsce', 'bsee', 'bsece', 'bsme', 'bsie', 'bscpe', 'bspe',
    ],

    /*
    |--------------------------------------------------------------------------
    | Negative terms
    |--------------------------------------------------------------------------
    |
    | The vocabulary that turns a mention of a person into something worth
    | reading first. These are deliberately NOT profanity — config/profanity.php
    | already rejects that outright, so by the time a submission reaches the
    | risk assessor it is clean of anything on that list. What is left is the
    | ordinary, unremarkable words people actually use to tear someone down.
    |
    | A term here scores nothing on its own. "Nakakahiya yung grades ko" is a
    | negative term with nobody on the other end of it, and the assessor leaves
    | it alone. It only counts once the post also points at a person.
    |
    | Matched on lowercased, accent-stripped text as whole words, so there is no
    | need to list casing variants. Multi-word phrases are fine.
    |
    */

    'negative_terms' => [

        // Filipino — plain insults that carry no profanity.
        'bobo', 'boba', 'tanga', 'tangang', 'engot', 'gunggong', 'timang',
        'pangit', 'pangets', 'chaka', 'baduy', 'mukhang', 'kadiri', 'nakakadiri',
        'nakakahiya', 'kahiya', 'walang kwenta', 'walang silbi', 'walang hiya',
        'plastik', 'plastic', 'sinungaling', 'manloloko', 'niloko', 'traydor',
        'mandaraya', 'magnanakaw', 'salot', 'epal', 'kapal ng mukha', 'makapal',
        'malandi', 'landi', 'maarte', 'feelingera', 'feelingero', 'pabebe',
        'chismosa', 'chismoso', 'tsismosa', 'intrigera', 'mukhang pera',
        'user', 'gold digger', 'pokpok',

        // Identity words, here because outing a classmate is the campus form
        // of harassment and a Facebook bullying violation in its own right.
        // They cut both ways: a warm post about a named friend trips them too.
        // Delete this line if the false positives annoy you more than the
        // misses do — the rest of the list does not depend on it.
        'bading', 'tomboy', 'bakla', 'silahis',

        // English.
        'ugly', 'fat', 'stupid', 'dumb', 'idiot', 'loser', 'pathetic',
        'disgusting', 'creep', 'creepy', 'liar', 'cheater', 'cheated',
        'fake', 'trash', 'worthless', 'embarrassing', 'clown', 'snake',
        'backstabber', 'attention seeker', 'pick me', 'insecure', 'desperate',
        'homewrecker', 'two timer', 'two-timer',

        // Call-outs: the grammar of a pile-on, with or without an insult.
        'iwasan niyo', 'iwasan nyo', 'wag kayo magtiwala', 'huwag kayo magtiwala',
        'wag niyo kaibiganin', 'beware', 'warning sa', 'exposing', 'expose',
        'exposed', 'shame on', 'ipagkalat', 'pakalat', 'kalat na',
    ],

    /*
    |--------------------------------------------------------------------------
    | Proper-name allowlist
    |--------------------------------------------------------------------------
    |
    | Capitalised word pairs that are not people. Without these, "Freedom Wall"
    | and "Batangas State" read as somebody's name the moment a post nearby says
    | anything unkind. Lowercased before comparison.
    |
    */

    'name_allowlist' => [
        'freedom wall', 'batangas state', 'state university', 'student council',
        'supreme student', 'good morning', 'good afternoon', 'good evening',
        'good luck', 'god bless', 'happy birthday', 'happy anniversary',
        'thank you', 'red spartans', 'dean list', "dean's list", 'holy week',
        'merry christmas', 'happy new', 'new year', 'first year', 'second year',
        'third year', 'fourth year', 'main campus', 'san juan', 'santo tomas',
    ],

    /*
    |--------------------------------------------------------------------------
    | Self-contact markers
    |--------------------------------------------------------------------------
    |
    | Phrases that say the number in this post belongs to whoever wrote it.
    | Somebody else's number is a privacy violation; your own is a classified
    | ad, and this wall runs on them — buy-and-sell, lost IDs, tutor hunting.
    |
    | A post carrying one of these keeps its contact flag anyway if it also
    | talks about another person ("si Maria", "number niya"), so "text me, I
    | have her number too" does not slip through.
    |
    */

    'self_contact_markers' => [
        'text me', 'txt me', 'message me', 'msg me', 'pm me', 'dm me',
        'contact me', 'reach me', 'call me', 'add me', 'hmu',
        'email me', 'mail me', 'e-mail me',
        'number ko', 'num ko', 'contact ko', 'email ko', 'account ko',
        'sa akin', 'sakin', 'akin', 'ako po', 'im selling', 'for sale',
        'selling', 'rfs', 'legit seller', 'interested',
    ],

    /*
    |--------------------------------------------------------------------------
    | Crisis phrases
    |--------------------------------------------------------------------------
    |
    | Phrases that read as someone in trouble. This category exists to get a
    | post read within minutes and to put a helpline in front of the person who
    | wrote it — never to reject them. A false positive here costs nothing more
    | than showing a number to somebody who did not need it, so the list leans
    | towards catching too much rather than too little.
    |
    */

    'self_harm_phrases' => [
        'gusto ko nang mamatay', 'gusto ko na mamatay', 'gusto ko mamatay',
        'ayoko na mabuhay', 'ayoko nang mabuhay', 'ayaw ko nang mabuhay',
        'wala na akong silbi', 'wala na akong dahilan', 'pagod na ako sa buhay',
        'sana mawala na ako', 'sana matapos na', 'tatapusin ko na',
        'magpapakamatay', 'magpakamatay', 'papatayin ko sarili ko',
        'end my life', 'end it all', 'kill myself', 'killing myself',
        'want to die', 'wanna die', 'better off dead', 'no reason to live',
        'cant do this anymore', "can't do this anymore", 'tired of living',
        'hurt myself', 'hurting myself', 'cut myself', 'cutting myself',
        'goodbye everyone', 'this is my last post', 'last post ko na',
    ],

    /*
    |--------------------------------------------------------------------------
    | Support resources
    |--------------------------------------------------------------------------
    |
    | Shown to the poster — not the admin — when a submission trips the
    | self-harm category. The post is still submitted and still queued; this is
    | only what comes back on the composer.
    |
    | VERIFY THESE NUMBERS BEFORE DEPLOYING. They are the published National
    | Center for Mental Health crisis lines as of writing, but a helpline that
    | has moved is worse than no helpline at all, and the campus guidance office
    | line below is a placeholder that nobody has filled in yet.
    |
    */

    'support' => [
        'message' => 'Your post is in. Before you go — if any of that was heavier than it looked, these people are awake and will talk to you.',
        'lines' => [
            'NCMH Crisis Hotline — 1553 (toll-free landline, nationwide)',
            'NCMH mobile — 0917-899-8727 or 0966-351-4518',
            'BSU Guidance and Counseling Office — see the campus directory',
        ],
    ],

];
