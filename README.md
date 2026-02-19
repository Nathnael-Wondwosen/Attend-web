## Finoteselam Attendance (Web)

Laravel 12 (PHP 8.2+) web app for managing and taking school attendance.

### Key Features
- Admin UI (dashboard, classes, students, attendance, reports)
- Teacher UI for taking attendance
- "Public take attendance" flow (token-based, no login for the teacher)
- REST API under `/api/v1` secured with Laravel Sanctum (plus a separate public namespace)

## Tech Stack
- PHP `^8.2`, Laravel `^12`
- MySQL (recommended for production) or SQLite (easy local dev)
- Node.js + Vite (asset build pipeline)

## Requirements
- PHP `8.2+`
- Composer `2.x`
- Node.js `18+` (or `20+`) + npm
- A database (MySQL recommended; SQLite supported for local)

## Quick Start (Local)
```bash
composer install
cp .env.example .env
php artisan key:generate

# Choose ONE database option:
# 1) SQLite (simple local)
touch database/database.sqlite
# If you're on Windows without `touch`:
# php -r "file_put_contents('database/database.sqlite','');"

# 2) MySQL
# Update DB_* in .env

php artisan migrate
npm install
npm run build

php artisan serve
```

### Dev Mode (runs Laravel + Vite watcher)
This repo includes a composer script:
```bash
composer run dev
```

## Important URLs
- Web
  - `/` redirects to `/login`
  - `/login` admin login page
  - `/teacher/login` teacher login page
  - `/takeattendance` take attendance UI
  - `/admin` admin dashboard
- API
  - Auth: `POST /api/v1/login`, `POST /api/v1/teacher/login`
  - Me: `GET /api/v1/me`
  - Public take-attendance: `/api/v1/public/v1/*`

## Environment (.env)
Start from `.env.example`.

Notes:
- Do not add stray lines (example: a single `S` line).
- Do not put inline comments after values like `DB_HOST=127.0.0.1 # comment`. Put comments on their own `#` line.
- Keep `DB_MIGRATIONS_TABLE=finot_migrations` so Laravel uses its own migration tracker table and does not collide with mother-system tables.
- Keep `FINOT_ALLOW_USER_AUTH_TABLES=false` on shared production DBs so Laravel does not create `users`/`password_reset_tokens`/`sessions` tables.
- If you use database-backed drivers, you must run migrations:
  - `SESSION_DRIVER=database`
  - `CACHE_STORE=database`
  - `QUEUE_CONNECTION=database`

## Production Deployment
### Standard Linux/VPS
```bash
composer install --no-dev --optimize-autoloader
php artisan migrate:install --force
php artisan migrate --force
php artisan admin:ensure
php artisan config:cache
php artisan route:cache
npm ci
npm run build
```

Admin bootstrap notes:
- `admin:ensure` is idempotent; it creates the admin if missing, or no-ops if present.
- Configure `.env` before running it:
  - `FINOT_ADMIN_USERNAME` (default: `admin`)
  - `FINOT_ADMIN_PASSWORD` (required when creating a new admin)
  - Optional: `FINOT_ADMIN_EMAIL`, `FINOT_ADMIN_FULL_NAME`, `FINOT_ADMIN_ROLE`, `FINOT_ADMIN_STATUS`
- To update an existing admin with env values, run:
```bash
php artisan admin:ensure --update-existing
```

Database switch runbook (future-safe):
```bash
# 1) Point .env to the new DB (DB_HOST/DB_DATABASE/DB_USERNAME/DB_PASSWORD)
# 2) Keep DB_MIGRATIONS_TABLE=finot_migrations
php artisan config:clear
php artisan migrate:install --force
php artisan migrate --force
php artisan admin:ensure
```

### cPanel Notes (Common Failure Points)
- The domain document root must point to the Laravel `public/` directory (recommended).
- PHP must be `8.2+` (MultiPHP Manager).
- `storage/` and `bootstrap/cache/` must be writable by PHP.
- `vendor/` must exist on the server:
  - If you do not have terminal access, upload `vendor/` from your local build OR ask the host/admin to run Composer.
- If you use Vite-built assets, ensure `public/build/manifest.json` exists (run `npm run build` before upload).
- If you use `/run` ops helper and want admin bootstrap there, set:
  - `FINOT_ADMIN_AUTO_ENSURE_ON_OPS_RUN=true`
  - plus the required `FINOT_ADMIN_*` values above.

## Troubleshooting
### 500 Internal Server Error
Common causes:
- Missing `vendor/` (Laravel can't `require ../vendor/autoload.php`)
- Wrong PHP version (< 8.2)
- Permissions on `storage/` or `bootstrap/cache/`
- Invalid `.env` formatting
- Using `CACHE_STORE=database` / `SESSION_DRIVER=database` without migrations

Check:
- Server error logs (cPanel: `Metrics -> Errors`)
- `storage/logs/laravel.log`

### 404 Page Not Found (not Laravel)
Usually means the domain is not pointing to `public/` or rewrites are not enabled. Verify the domain's document root and `.htaccess`.

## Security
- Never commit a real `.env` with credentials.
- Rotate any credentials that were shared publicly.

## License
Proprietary (update this section if you intend to open-source).
