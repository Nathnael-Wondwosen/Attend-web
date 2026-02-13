Finot-Attendance – Execution Plan
=================================

Context
- Stack: Laravel on cPanel, MySQL (existing roster schema). API-first for future mobile app.
- Priorities: very fast scan endpoint, accurate data, minimal dependencies (Blade + Alpine).

Milestones
1) Scaffold app
   - Create Laravel project outside `public_html`.
   - Configure `.env` for production defaults (APP_KEY, APP_ENV=production, APP_DEBUG=false).
   - Set DB connection to existing MySQL with SSL.
   - Symlink `public/` into `public_html`.
2) Data model
   - Add migrations for `att_sessions`, `att_attendance`, `att_session_tokens`.
   - Enforce unique `(session_id, student_id)` and FKs to `classes`, `students`, `teachers`.
   - Seed: admin user, sample teacher, class mapping for tests.
3) Auth & permissions
   - Install Sanctum; issue tokens.
   - Policies: teacher can manage sessions/attendance only for assigned classes (`class_teachers`).
4) Core API (/api/v1)
   - Sessions: open, rotate-token, close, show, list by class/date.
   - Attendance: scan (token + student_id, idempotent), manual override, CSV export/import.
   - Reports: per-class daily/weekly, per-student term summary (CSV).
5) Web UI (Blade + Alpine)
   - Teacher dashboard: class list, open/close session, QR display, live counts, overrides.
   - Admin: teacher-class mapping, report exports, backup trigger.
6) Ops & perf
   - Cron: `* * * * * php artisan schedule:run`; optional `queue:work --stop-when-empty`.
   - Enable OPcache, `config:cache`, `route:cache`; gzip; throttles on scan/auth.
   - HTTPS redirect via .htaccess; storage/cache writable.
   - Nightly mysqldump of roster + attendance tables (retain 7–30 days).
7) Testing
   - Unit: no double mark, token expiry, class membership check.
   - Feature: open→scan→close flow, reports, CSV import/export.

Next actions
- Confirm installed PHP/Laravel/composer versions on the server.
- If composer is available, scaffold the Laravel app and add the attendance migrations from milestone 2.
