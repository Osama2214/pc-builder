# Free deployment (backend + frontend)

Live architecture, entirely on free tiers:

| Layer | Provider | Notes |
|---|---|---|
| Backend (Laravel API) | [Render](https://render.com) — Free Web Service | Docker-based, auto-deploys on push to `main` |
| Database | [Neon](https://neon.tech) — Free Postgres | Persistent, independent of Render's ephemeral disk |
| Uploaded images | [Cloudflare R2](https://developers.cloudflare.com/r2/) | S3-compatible, persistent, 10GB free |
| Frontend (static HTML/JS) | [Vercel](https://vercel.com) | No build step, root directory set to `frontend` |

## Why Postgres + R2 instead of SQLite + local storage

Render's free plan has no persistent disk: **any file written to the container's
local filesystem is lost on every restart**, not just on redeploys — including
the quiet restart that happens when a free instance spins back up after being
idle. That made SQLite and locally-stored uploaded images unsuitable for
anything beyond a single session, since admin-added products/images would
vanish unpredictably. Moving the database to Neon (external Postgres) and the
`public` disk to Cloudflare R2 (external S3-compatible storage) takes both out
of the ephemeral container entirely, so they survive restarts and redeploys.

## Files that make this work

- `backend/Dockerfile` / `backend/docker-entrypoint.sh` — builds the Laravel
  app for Render's Docker runtime; on boot it runs migrations and seeds the
  real catalog only if the `products` table is empty
  (`php artisan catalog:seed-if-empty`, see
  `backend/app/Console/Commands/SeedCatalogIfEmpty.php`).
- `backend/config/filesystems.php` — the `public` disk's driver is
  `env('PUBLIC_DISK_DRIVER', 'local')`, so it stays `local` for local dev and
  switches to `s3` (pointed at R2) in production via one env var.
- `frontend/js/api.js` — picks the API base URL based on `window.location.hostname`
  (localhost vs. the deployed Render URL).

## Backend environment variables (Render → Environment tab)

```
APP_NAME=PC Builder
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...
APP_URL=https://<your-render-service>.onrender.com

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
BCRYPT_ROUNDS=12
LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=pgsql
DB_URL=<Neon connection string, postgresql://...?sslmode=require>

SESSION_DRIVER=database
SESSION_LIFETIME=120
QUEUE_CONNECTION=database
CACHE_STORE=database

PUBLIC_DISK_DRIVER=s3
AWS_ACCESS_KEY_ID=<R2 access key id>
AWS_SECRET_ACCESS_KEY=<R2 secret access key>
AWS_DEFAULT_REGION=auto
AWS_BUCKET=<R2 bucket name>
AWS_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com
AWS_URL=<R2 public bucket URL, e.g. https://pub-xxxx.r2.dev>
AWS_USE_PATH_STYLE_ENDPOINT=true

OPENROUTER_API_KEY=<your key>
OPENROUTER_MODEL=deepseek/deepseek-chat
```

Render service settings: Root Directory `backend`, Runtime `Docker`, Instance
Type `Free`, Health Check Path `/up`.

## Known limitations of the free tier

- The Render free instance spins down after ~15 minutes of inactivity; the
  first request after that takes 30-50s to wake it back up.
- Neon's and R2's free tiers have their own usage caps (Neon: compute
  hours/storage; R2: 10GB storage, 1M Class A / 10M Class B ops per month).
  Fine for a portfolio/demo project, not sized for real traffic.

## Frontend (Vercel)

1. Import the GitHub repo in Vercel.
2. Root Directory: `frontend`.
3. Framework Preset: `Other`, no build/install command needed (plain static
   HTML/CSS/JS).
4. Deploy.
