# BSU Freedom Wall

Anonymous submission site: a Laravel + Inertia + Vue 3 app (submission form +
public wall + admin dashboard), backed by MySQL, with approved posts
reviewed on-site and ready for manual posting anywhere you choose. See
[plan.md](plan.md) for the original design notes and [skill.md](skill.md) for
the reusable architecture blueprint.

## Setup

```bash
composer install
cp .env.example .env       # fill in DB_*, ADMIN_*, IP_SALT, TURNSTILE_* vars
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

- Public wall + submission composer: `/wall` (`/` redirects here)
- Admin dashboard: `/admin/login`

## Notes

- CAPTCHA (Cloudflare Turnstile) is skipped gracefully when its env vars are
  left blank, so local dev works without it. Fill in
  `TURNSTILE_SECRET_KEY`/`VITE_TURNSTILE_SITE_KEY` before deploying if you
  want anti-spam protection.
- The admin dashboard includes a manual posting workflow: approve a submission,
  then use `Copy text` and `Download images` from the approved list when you
  are ready to post it to Facebook or another platform.
- Uploaded images live at `storage/app/public/uploads/`, served by the app at
  `/media/uploads/...` so shared hosting does not need `storage:link`.
- Rate limiting (3 submissions per IP per 5 minutes) is defined in
  `AppServiceProvider` via `RateLimiter::for('submission', ...)`.
- Migrated from an earlier Node/Express + Vue SPA prototype to run natively
  on PHP/MySQL shared hosting (no native module compilation, no separate
  CORS/JWT setup â€” Inertia serves everything same-origin).
