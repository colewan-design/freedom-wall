# BSU Freedom Wall

Anonymous submission site: a Laravel + Inertia + Vue 3 app (submission form +
public wall + admin dashboard), backed by MySQL, with approved posts
auto-posted to a Facebook Page via the Graph API. See [plan.md](plan.md) for
the original design notes and [skill.md](skill.md) for the reusable
architecture blueprint.

## Setup

```bash
composer install
cp .env.example .env       # fill in DB_*, ADMIN_*, IP_SALT, FB_* vars
php artisan key:generate
php artisan migrate --seed  # creates the submissions table + seeds the admin user

npm install
npm run build                # or `npm run dev` for local development
```

## Run (dev)

```bash
php artisan serve      # backend, defaults to http://127.0.0.1:8000
npm run dev            # Vite dev server for HMR (pinned to port 5210, see vite.config.js)
```

- Public submission form: `/`
- Public approved-posts wall: `/wall`
- Admin dashboard: `/admin/login`

## Notes

- CAPTCHA (Cloudflare Turnstile) and Facebook posting are both skipped
  gracefully when their env vars are left blank, so local dev works without
  either configured. Fill in `TURNSTILE_SECRET_KEY`/`VITE_TURNSTILE_SITE_KEY`
  and `FB_PAGE_ID`/`FB_PAGE_ACCESS_TOKEN` before deploying.
- `FB_PAGE_ACCESS_TOKEN` must be a Facebook Page access token (or Business
  System User token) with `pages_manage_posts` for the target Page. The old
  `publish_actions` permission was deprecated years ago and will fail with
  `(#200) The permission(s) publish_actions are not available`.
- Uploaded images live at `storage/app/public/uploads/`, served by the app at
  `/media/uploads/...` so shared hosting does not need `storage:link`.
- Rate limiting (3 submissions per IP per 5 minutes) is defined in
  `AppServiceProvider` via `RateLimiter::for('submission', ...)`.
- Migrated from an earlier Node/Express + Vue SPA prototype to run natively
  on PHP/MySQL shared hosting (no native module compilation, no separate
  CORS/JWT setup â€” Inertia serves everything same-origin).
