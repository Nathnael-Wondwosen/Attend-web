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
