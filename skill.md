---
name: anonymous-fb-freedom-wall
description: Build a website that accepts anonymous text/image submissions, queues them for admin moderation, and auto-posts approved submissions to a Facebook Page via the Graph API. Use this skill whenever the user wants to build a "freedom wall," "confession page," "anonymous submission site," or any site/bot that collects anonymous posts and publishes approved ones to a Facebook Page (or similar social page). Covers full architecture: submission form, moderation queue, database schema, Facebook Page Access Token setup, Graph API posting, anti-spam/CAPTCHA, and deployment. Trigger this even if the user only describes part of the workflow (e.g. "just the FB posting part" or "just the admin dashboard") — pull the relevant section rather than skipping the skill.
---

# Anonymous Freedom Wall → Facebook Auto-Poster

A reusable blueprint for building anonymous-submission websites that moderate content and auto-publish approved posts to a Facebook Page.

## When to use this

Trigger on requests like:
- "Build a freedom wall / confession page site"
- "Anonymous submission form that posts to Facebook"
- "Moderation queue + auto-post to a Facebook page"
- Any sub-piece of this (just the form, just the admin panel, just the Graph API integration)

## Core Architecture

```
Anonymous submitter → Submission form → DB (status: pending)
                                              ↓
                                    Admin dashboard (approve/reject)
                                              ↓
                                   Approved → Facebook Graph API → Live on Page
```

Always build in this order unless the user asks otherwise: **DB schema → submission API → admin dashboard → Facebook integration → anti-spam hardening**. Facebook integration goes last because it depends on having approved content to test against, and token setup is the most fragile part — don't let it block earlier progress.

## 1. Database Schema (default)

Use this schema unless the user specifies otherwise. SQLite for dev/small scale, Postgres for production:

```sql
CREATE TABLE submissions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  content TEXT NOT NULL,
  image_url TEXT,
  status TEXT NOT NULL DEFAULT 'pending', -- pending | approved | rejected
  submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME,
  fb_post_id TEXT,
  ip_hash TEXT NOT NULL
);
```

Never store raw IP addresses — hash them (e.g. `sha256(ip + salt)`) for rate-limiting/abuse tracking only. This is a privacy requirement for anonymous-submission products, not optional.

## 2. Submission Form (public-facing)

Minimum viable requirements:
- Textarea with a character cap (500–1000 chars is typical for confession-wall style content)
- Optional image upload — validate file type/size server-side, never trust client-side checks alone
- CAPTCHA (hCaptcha or Cloudflare Turnstile — prefer these over reCAPTCHA for privacy-conscious anonymous products)
- Server-side rate limiting by hashed IP (e.g. 1 submission per N minutes)
- Immediate confirmation message on submit — the user never sees whether it was approved in real time, since that defeats moderation

## 3. Admin Dashboard

- Auth-gate the whole dashboard — simple email/password is sufficient for one or a few admins; don't over-engineer with a full auth system unless the user has multiple mod teams
- List pending submissions newest-first, with Approve / Reject actions
- Approve → immediately triggers the Facebook post (see §4) and writes `fb_post_id` + `reviewed_at` back to the row
- Reject → soft state change only, keep the row for spam-pattern review, never hard-delete by default
- Allow light editing before approval (typo fixes) but flag edited posts distinctly in the DB (`was_edited` boolean) if the user wants an audit trail

## 4. Facebook Graph API Integration

**Setup checklist (walk the user through this in order):**
1. Create a Meta App at developers.facebook.com, type **Business**
2. Add the **Facebook Login** product (required even for a backend-only/no-UI-login flow — it's how you obtain tokens)
3. Request `pages_manage_posts` permission (add `pages_read_engagement` too if the user wants engagement stats later)
4. Generate a User Access Token via Graph API Explorer, then exchange it for a **Page Access Token**
5. Convert to a **long-lived token** (~60 days), or set up a **Business System User token** — strongly prefer the System User token since it doesn't expire and is the correct approach for unattended server-to-server posting
6. Store the token in `.env` / secrets manager — never commit it, never expose it client-side

**Important nuance to tell the user:** if they are the admin of the target page and the app is self-managed (not distributed to other page owners), they do **not** need to go through Meta's public App Review process. Only mention App Review as a requirement if the user says other people will authorize the app to manage their own pages.

**Posting call:**
```
POST https://graph.facebook.com/v21.0/{page-id}/feed
  message=<approved text>
  access_token=<page_access_token>
```
- Always hardcode an explicit API version (e.g. `v21.0`) in the URL. Omitting it routes to the oldest available version, which can be retired without warning.
- Capture the returned post ID and store it as `fb_post_id`.
- Wrap the call in error handling for expired/invalid tokens — on failure, leave the row `approved` but flag it (`fb_post_id IS NULL`) for the admin to retry manually rather than silently losing the post.

## 5. Anti-Abuse Layer

Apply all of these by default for anonymous-submission products; don't wait to be asked:
- CAPTCHA on submit
- IP-hash-based rate limiting
- Basic profanity/hate-speech filter before content even reaches the moderation queue (reduces admin burden, doesn't replace human review)
- HTTPS-only, admin routes behind auth
- No raw PII persisted anywhere

## 6. Suggested Stack (default recommendation)

| Layer | Default | Reasoning |
|---|---|---|
| Frontend | Plain HTML/CSS/JS or React (Vite) | Keep the public form fast and mobile-first |
| Backend | Node.js + Express | Best Graph API SDK/community support |
| DB | SQLite (dev) → Postgres (prod) | Low friction to start, easy migration path |
| Anti-spam | hCaptcha / Cloudflare Turnstile | Free tier, privacy-respecting |
| Hosting | Render/Railway/Fly.io (backend) + Vercel/Netlify (frontend) | Free/cheap tiers, simple deploys |

Deviate from this table only if the user names a different stack they already use.

## 7. Common Follow-Up Questions to Anticipate

Proactively surface these to the user early rather than waiting for them to hit the issue:
- Will images be allowed, or text-only for v1? (affects storage/hosting choice)
- How many admins need dashboard access? (affects auth complexity)
- Should there be posting throttle (e.g. max 1 auto-post every 30 min) to avoid flooding the page?
- Does the user want a public "recently approved" feed on the website itself, separate from Facebook?

## 8. Extending Beyond v1

If asked to extend later: multi-platform posting (Instagram/Threads use the same Graph API family — reuse the token setup pattern), scheduled/staggered posting via `published=false` + `scheduled_publish_time`, or an AI pre-filter pass before human moderation. Treat these as v2 — don't build them unprompted into a v1 request.