# Moderation: detecting bullying and policy violations

How this wall decides which posts a human should read first, what it
deliberately does not decide, and what the next two steps would cost.

## The shape of the thing

Every wall submission already goes through a human. `SubmissionController::store`
queues it as `pending`, an admin reads it in the dashboard, and only then does it
appear. The CTA on the wall says so out loud: *"A human reads every submission
before it goes up."*

That fact is what makes automated detection safe to add here. The rules do not
decide anything — **they reorder the queue and explain themselves.** A missed
case is read in its normal turn, which is exactly what happens to every post
today. A false positive costs an admin four seconds.

This is the whole design. Anything that auto-rejects on a freedom wall is
censorship with extra steps, and on a wall where `rant` is a literal category,
venting and harassment look alike to every classifier you can build.

```
submission ──▶ profanity filter ──▶ risk assessor ──▶ pending queue ──▶ human
               (rejects outright)   (scores, never    (worst first)      (decides)
                                     rejects)
```

## What already existed

`ContentFilterService` (`config/profanity.php`) is a wordlist matcher, and a good
one — it handles casing, accents, leetspeak (`p0ta`), masking (`f**k`), stretched
letters (`fuuuck`), spaced-out letters (`p u t a`) and common endings. It runs on
the wall, the feed, threads, chat and DMs, and it rejects outright.

**It cannot detect bullying, and no wordlist can.** Bullying is usually written
in clean words:

> ask 3-B's class president why she really dropped out last sem, kayo na bahala

Zero blocked terms. Names a person. That is the gap Tier 1 fills.

---

# Tier 1 — rules (built)

No dependencies, no network, no API keys, no cost. Pure PHP, runs inline at
submission time in well under a millisecond.

### Files

| File | What it does |
| --- | --- |
| `app/Services/ContentRiskService.php` | The assessor. Three categories, scored and summed. |
| `app/Services/ContentRisk.php` | The verdict: score, categories, matched evidence. |
| `config/moderation.php` | Every term list, weight and threshold. Edit here, not in code. |
| `app/Console/Commands/AssessSubmissionRisk.php` | `php artisan wall:assess-risk` — backfills and rescores. |
| `tests/Unit/ContentRiskServiceTest.php` | 35 cases, weighted towards false positives. |
| `tests/Feature/SubmissionRiskTest.php` | The wiring: scored on the way in, queue order, rejection labels. |

### The three categories

**`contact_details`** — weight 60. A phone number (`09171234567`, `0917 123 4567`,
`+63 917 123 4567`), an email, a Facebook/Instagram/TikTok profile link, or a
student number. Almost never a false positive, and a Facebook **Privacy
Violation** on its own, whoever the post is about.

Two carve-outs that matter on a campus wall:

- *Your own* number is a classified ad, not doxxing. A post saying "for sale,
  text me 0917…" is not flagged — unless it also talks about another person
  ("si Maria", "number niya"), which closes the "text me, I have her number
  too" hole. The markers live in `self_contact_markers`.
- `SY 2024-2025` is not a student number. The student-number pattern explicitly
  refuses anything whose second half is a year.

**`named_target`** — weight 45. The post points at one identifiable person **and**
says something unkind near them. *Neither half counts alone* — that is the single
most important rule in the file:

- insults with nobody on the other end are someone venting, which is what the
  wall is for → `"Ang bobo ko sa Calculus"` is clean
- a name with nothing bad attached is a birthday greeting → `"Happy birthday kay
  Maria!"` is clean
- both together → flagged

Four ways a person gets identified:

| Signal | Example | Needs an insult nearby? |
| --- | --- | --- |
| Tagalog name particle | `si Maria`, `kay Jhen` | no, anywhere in the post |
| Handle | `@jhensantos` | no |
| Course + section | `BSIT 2-A`, `bsit-2a` | no |
| Capitalised name pair | `Maria Santos` | yes, within 8 words |

The particle detector is the good one. Tagalog marks personal names with
`si / ni / kay / kina / nina / sina`, which beats any capitalisation rule and is
the exact grammar a call-out post uses. `yung` and `nung` are deliberately **not**
particles — they point at anything (`yung Calculus exam`).

Proximity is measured in **words, not bytes**, so accents and casing cannot knock
the arithmetic out.

**`self_harm`** — weight 70. Crisis phrases in English and Taglish. This category
exists to do two things and neither of them is blocking: get the post read within
minutes, and put a helpline in front of the person who wrote it (a quiet note on
the wall page — *not* inside the composer, which closes the instant a post goes
through). A false positive here costs nothing but showing a number to someone who
did not need it, so the list leans towards catching too much.

### Scores

Summed, capped at 100. Bands in `config/moderation.php`: `low` 1, `medium` 40,
`high` 70. The pending queue sorts worst-first, then oldest-first, so two posts at
the same risk still queue fairly.

Each card in the dashboard shows the band, the categories, and **the exact
substrings that tripped it** — an admin has to be able to disagree with a flag
without rereading the post hunting for what caused it.

### Running it on posts that already exist

```bash
php artisan wall:assess-risk              # unassessed rows only (safe to re-run)
php artisan wall:assess-risk --all        # rescore everything after editing the term lists
php artisan wall:assess-risk --status=pending
```

`risk_checked_at` being null means "never assessed", which is a different thing
from a score of zero.

### Known false positives, accepted on purpose

- **Identity words.** `bading`, `tomboy`, `bakla` are in `negative_terms` because
  outing a classmate is the campus form of harassment and a Facebook bullying
  violation. They cut both ways: a warm post about a named friend trips them too.
  One line in the config, flagged with a comment, delete it if the misses are
  cheaper than the noise.
- **`user`.** Filipino slang for someone who uses people, but also an ordinary
  English word. Protected only by the named-target gate.
- **Lowercase names.** `si maria` does not flag — the capital is what keeps
  `si ate`, `si kuya` and `si sir` out. A real gap in casual writing.

### What Tier 1 cannot do, honestly

Sarcasm, context, in-jokes, implied threats. This is invisible to it:

> alam na ng lahat kung bakit siya umalis

Devastating to the right reader, clean to every rule here. Rules buy precision
and pay for it in recall. They catch the blatant cases and miss the clever ones —
survivable only because a human is still the decider.

Images are **not assessed at all.** Campus bullying is often a photo of someone,
and there is no free, shared-hosting-compatible way to look at one. Images stay
fully human-reviewed.

---

# Tier 2 — a classifier trained on this wall's own history

**Not built. The groundwork is in.**

The unlock: this project already owns a labelled dataset that no off-the-shelf
model has. Every row in `submissions` carries an approve/reject decision made by
your own admins, on your own wall, in your own Taglish.

Train TF-IDF + logistic regression **locally**, export the weights as a PHP array,
and inference on the server is tokenise-and-sum — roughly fifty lines and **zero
composer dependencies**, which matters because the production `composer.lock` sits
ahead of the repo's and running `composer install` there would silently downgrade
Laravel. Nothing new needs to be installed on the Hostinger box.

What it would catch that Tier 1 cannot: the vocabulary of *this* wall. Slang,
nicknames, running jokes, the specific words that precede a pile-on here.

**The blocker was labels, and that is now fixed.** `reject()` used to record only
*that* a post was turned down, so "rejected" lumped bullying in with spam,
duplicates and off-topic. It now records a reason, chosen from a dropdown on each
card:

```
bullying · privacy · hate · sexual · spam · off_topic · other
```

Stored in `submissions.rejection_reason`. **Nothing reads it yet.** It exists to
accumulate. Train on it when there are a few thousand labelled rows — check with:

```sql
SELECT rejection_reason, COUNT(*) FROM submissions
WHERE status = 'rejected' GROUP BY rejection_reason;
```

Caveat worth knowing up front: a model trained on this queue reproduces this
queue's judgment, biases included, and only covers what these admins have already
seen.

---

# Tier 3 — hosted models

**Not built.** Two routes, both viable.

**Free tiers.** Google's Perspective API is genuinely free and purpose-built
(`TOXICITY`, `INSULT`, `THREAT`, `IDENTITY_ATTACK`) — but weak on Taglish, which
is the language this wall is actually written in. Gemini, Groq and Cloudflare
Workers AI free tiers give real LLM quality at zero cost at campus volume,
rate-limited with no uptime guarantee.

**Paid.** Claude Haiku with the Community Standards as a rubric, returning a
level, categories and the span it reacted to. Fractions of a cent per post. It
handles code-switching, which is the thing rules and bag-of-words both fail at,
and it is the only tier that can read an image.

Whichever: **run it in a queued job, never inline.** The poster must not wait on a
network call, and an API outage must not break submissions. Fail open — on error,
mark unknown and let the post enter the queue normally.

---

## On "Facebook page policies"

Meta has **no API that answers "would this violate?"** You cannot check against
their enforcement programmatically. What you can do is encode the Community
Standards sections that matter as a rubric — which is what Tier 1's categories
and the rejection reasons are modelled on:

| Standard | Covered by |
| --- | --- |
| Privacy Violations (doxxing) | Tier 1 `contact_details` — mechanically, well |
| Bullying & Harassment | Tier 1 `named_target` — blatant cases only |
| Suicide & Self-Injury | Tier 1 `self_harm` — support path, never a rejection |
| Hate Speech | the profanity list, partially; needs Tier 2/3 |
| Violence & Incitement | **nothing** — needs Tier 2/3 |
| Adult Nudity | **nothing** — images are not assessed |

Note that cross-posting to the Page is **documented but not built**: `fb_post_id`
exists in the submissions table and `FB_PAGE_ACCESS_TOKEN` is in `.env`, but no
code calls the Graph API. Approved posts are copied out of the dashboard by hand.
The day that becomes automatic, the gaps in the table above start carrying real
risk to the Page — a violating auto-post is how a Page gets restricted.

Facebook also treats private individuals as more protected than public figures,
which is exactly why `named_target` is built around "does this point at one
identifiable person" rather than the fuzzier "is this bullying".

---

## Turning it off

```env
CONTENT_RISK_ENABLED=false
```

Nothing breaks. The queue goes back to oldest-first and scores stop being written.
No submission has ever been rejected by any of this, so there is nothing to undo.

## Before deploying

- **Verify the helpline numbers** in `config/moderation.php` under `support`.
  They are the published NCMH crisis lines as of writing, and the campus guidance
  office line is still a placeholder. A helpline that has moved is worse than no
  helpline at all.
- Run `php artisan migrate --force` on the server — this adds `risk_score`,
  `risk_flags`, `risk_checked_at` and `rejection_reason` to `submissions`.
- Run `php artisan wall:assess-risk` once afterwards, or the dashboard shows
  nothing until new posts arrive.
