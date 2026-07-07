# BSU FW — Anonymous Freedom Wall Website
### Project Plan

---

## 1. Overview

A website where BSU students can submit anonymous posts (confessions, rants, crushes, etc.). Submissions go into a moderation queue; once approved by an admin, the post is automatically published to the **BSU FW Facebook Page** via the Graph API.

**Core flow:**
```
Anonymous submitter → Submission form → Pending queue (DB)
                                              ↓
                                    Admin dashboard (approve/reject)
                                              ↓
                                   Approved → Facebook Graph API → Live on Page
```

---

## 2. Goals & Non-Goals

**Goals**
- Simple, fast anonymous submission form (text + optional image)
- Spam/abuse protection without requiring login
- Lightweight admin review dashboard
- One-click approve → auto-post to Facebook Page
- Deployable on a small budget (no login walls, no heavy infra)

**Non-Goals (v1)**
- No public app / App Review from Meta needed (self-managed page only)
- No user accounts for submitters
- No multi-platform posting (Facebook only for now — can extend later)

---

## 3. Tech Stack

| Layer | Choice | Notes |
|---|---|---|
| Frontend | Vue 3 SPA (Vite) | Single app: submission form, public approved-posts feed, admin dashboard behind a route |
| Backend | Node.js + Express | Familiar, good Graph API SDK support |
| Database | SQLite (dev) → PostgreSQL (prod) | SQLite is fine for low-medium traffic |
| Admin dashboard | Vue route (`/admin`) gated by single admin login (JWT/session) | One admin account for v1, no multi-user roles |
| Anti-spam | hCaptcha or Cloudflare Turnstile | Free, privacy-respecting |
| Facebook integration | Graph API v21+ (`/{page-id}/feed`) | Requires Page Access Token |
| Hosting | Render / Railway / Fly.io (backend), Vercel/Netlify (frontend) | Free/cheap tiers available |
| Image storage | Local disk (`/uploads`) for v1, served statically | Cloudinary can replace this later if traffic grows |

**Decisions locked in (v1):**
- Images: **allowed** (optional upload alongside text)
- Public feed: **yes** — a `/wall` page on the Vue SPA lists approved posts in addition to the Facebook Page
- Admins: **single admin account**, simple password login

> Since you're on Windows: local dev works fine with Node.js LTS via the official Windows installer or `nvm-windows`. Use **VS Code** + the **Thunder Client** or **REST Client** extension to test API endpoints without needing Postman.

---

## 4. Database Schema (initial)

**`submissions` table**

| Column | Type | Notes |
|---|---|---|
| id | INTEGER PK | |
| content | TEXT | The message |
| image_url | TEXT (nullable) | If image uploads supported |
| status | TEXT | `pending`, `approved`, `rejected` |
| submitted_at | DATETIME | |
| reviewed_at | DATETIME (nullable) | |
| fb_post_id | TEXT (nullable) | Set after successful FB post |
| ip_hash | TEXT | Hashed IP for rate-limiting/abuse tracking (not stored raw) |

---

## 5. Feature Breakdown

### 5.1 Public Submission Page
- Textarea (character limit ~500–1000)
- Optional image upload (JPEG/PNG, size-limited, auto-compressed)
- CAPTCHA challenge before submit
- Rate limit: e.g. 1 submission per IP per X minutes
- Basic client + server-side profanity/spam filter (optional first-pass filter before human review)
- Confirmation message: "Submitted! Our team will review it before posting."

### 5.2 Admin Dashboard
- Login-protected (simple email/password or magic link — no need for full auth system)
- List of pending submissions, newest first
- Approve / Reject buttons
- Optional: edit text slightly before approving (e.g. fix typos) — flag if edited
- Approved → triggers Facebook post automatically
- Rejected → soft-deleted, logged for spam pattern review
- Simple stats: pending count, approved today, rejected today

### 5.3 Facebook Auto-Posting
- Use a **long-lived Page Access Token** (or Business System User token — doesn't expire, best option)
- On approve: `POST /v21.0/{page-id}/feed` with `message` param
- Store returned `fb_post_id` in DB for reference/audit
- Handle errors gracefully (token expired, rate-limited, etc.) — retry queue or admin alert

### 5.4 Security & Abuse Prevention
- CAPTCHA on submission
- Rate limiting (per IP)
- No PII stored beyond hashed IP (for abuse tracking only, not identity)
- Admin routes protected behind auth + HTTPS only
- `.env` file for secrets (Page token, App Secret) — **never commit to Git**
- Basic content filter for slurs/hate speech before it even reaches the queue

---

## 6. Facebook App Setup Checklist

- [ ] Create Meta App (type: Business) at developers.facebook.com
- [ ] Add "Facebook Login" product
- [ ] Request `pages_manage_posts` permission (and `pages_read_engagement` if tracking engagement later)
- [ ] Generate User Access Token via Graph API Explorer
- [ ] Exchange for Page Access Token
- [ ] Convert to long-lived token, or set up a Business System User token (recommended — doesn't expire)
- [ ] Store token securely in backend `.env`
- [ ] Test a manual POST to `/v21.0/{page-id}/feed` before wiring up automation

---

## 7. Milestones

| Phase | Deliverable | Est. time |
|---|---|---|
| 1 | Submission form + backend API + DB | 2–3 days |
| 2 | Admin dashboard (list, approve, reject) | 2–3 days |
| 3 | Facebook Graph API integration + token setup | 1–2 days |
| 4 | Anti-spam (CAPTCHA, rate limit, filters) | 1 day |
| 5 | Styling/branding (match BSU FW green/yellow theme) | 1–2 days |
| 6 | Testing + deploy | 1–2 days |

**Total estimate:** ~1.5–2 weeks part-time

---

## 8. Open Questions / Decisions Needed

- Will images be allowed in submissions, or text-only for v1?
- Who are the admins/moderators, and how many people need dashboard access?
- Should there be a delay/queue (e.g. post at most every 30 min) to avoid flooding the FB page?
- Do you want a public "recent approved posts" feed on the website itself, or Facebook-only?

---

## 9. Future Extensions (v2+)

- Multi-platform posting (Instagram, Twitter/X)
- Auto-scheduling (spread approved posts throughout the day)
- Basic analytics dashboard (submissions per day, approval rate)
- AI-assisted pre-filtering of spam/hate speech before human review