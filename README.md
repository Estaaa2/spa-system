# Levictas — Spa Management Platform

Levictas is a web-based integrated management platform for spa businesses in Cavite,
Philippines. It handles spa discovery and online booking for customers, day-to-day
operations (scheduling, attendance, staff, finance, HR) for spa staff, and platform-wide
oversight for administrators — with a Decision Support System layered on top for
descriptive analytics.

This is a 4th-year IT capstone project (Thesis A/B). Thesis A covered design and initial
build; Thesis B focuses on completing remaining features, stabilizing the codebase, and
deploying for real spa businesses rather than a defense demo only.

## Tech Stack

- **Backend:** Laravel (PHP), MySQL
- **Frontend:** Blade templates, Tailwind CSS, Alpine.js, vanilla JavaScript, Vite
- **Payments:** PayMongo (checkout sessions + webhooks)
- **Authorization:** Spatie Laravel Permission (role-based access control, branch-scoped)
- **Charts:** Chart.js (DSS, Reports)
- **Deployment:** Custom `sftp-git-deploy` Node.js tool (`deploy-tool/`), VPS with nginx +
  PHP-FPM

## Features

**Customer side**
- Browse verified spas and public branch listings
- Online booking with PayMongo checkout (20% reservation fee)
- Appointment management, reschedule requests, ratings

**Staff side**
- Dashboard with live-polling KPIs, revenue, and appointment timeline
- Appointment booking, scheduling, and reassignment workflow
- Attendance & leave management
- Services, staff, and branch management
- Workforce & Finance suite: hiring, interviews, payroll, revenue, billing & expenses
- Decision Support System (descriptive analytics) and operational reports

**Admin side**
- Platform-wide oversight of registered spas, users, and default role permissions

Role-based access is enforced at both route middleware and Blade template layers
(Spatie Permissions), with branch-level permission overrides on top of role defaults.

## Getting Started

### Prerequisites

- PHP 8.2+ (production runs 8.4-fpm)
- Composer
- Node.js & npm
- MySQL

### Installation

```bash
git clone <repo-url>
cd <project-folder>
composer install
npm install
cp .env.example .env
php artisan key:generate
```

### Environment Configuration

Set the following in `.env` at minimum:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

PAYMONGO_SECRET_KEY=       # sk_test_... for local/dev, sk_live_... for production
PAYMONGO_WEBHOOK_SECRET=

MAIL_MAILER=
MAIL_HOST=
MAIL_PORT=
MAIL_USERNAME=
MAIL_PASSWORD=
```

PayMongo mode (test vs live) is determined entirely by the `sk_test_` / `sk_live_` prefix
on `PAYMONGO_SECRET_KEY` — there's no separate mode flag.

### Database

```bash
php artisan migrate
php artisan db:seed --class=RolePermissionSeeder
```

> **Important:** `migrate:fresh` wipes Spatie's permission tables — always re-run
> `RolePermissionSeeder` after a fresh migration, or every role/permission check will
> silently fail. Do **not** re-run this seeder on top of a database with live imported
> data; it's meant for fresh databases only.

### Build Assets

```bash
npm run dev     # local development
npm run build   # production build
```

### Storage

```bash
php artisan storage:link
```

Uploaded files under `storage/app/public/` are not in version control — if you're moving
between environments, rsync this directory separately.

### File Permissions

`storage/` and `bootstrap/cache/` need `775` permissions for Laravel to write to them at
runtime. A blanket `755` chmod will silently break uploads and caching.

### Local Scheduler Testing

```bash
php artisan schedule:work
```

## Deployment

Production deploys go through a custom `sftp-git-deploy` Node.js tool in `deploy-tool/`
(kept in its own subfolder with its own `package.json` to avoid ES module conflicts with
the main Vite `package.json`). Server-side steps — `composer install`, `npm run build`,
`php artisan migrate`, cache clears — are still run manually over SSH; the deploy tool
does not automate those.

Production server: VPS with nginx, PHP 8.4-fpm, and a cron entry running Laravel's
scheduler (`php artisan schedule:run` every minute).

The PayMongo webhook route requires an nginx `allow all` exact-match `location` block
placed **before** the IP-restricted `location /` block, or webhook calls will be blocked.

## Notes for Contributors

- This project uses branch-scoped RBAC: Spatie role permissions are the coarse gate,
  but several controllers layer additional row-level ownership filters on top (e.g.
  branch/spa scoping). Don't assume a passing `@can` check means the query is already
  scoped correctly — check the controller.
- Live dashboard/appointment data uses polling (not WebSockets/Reverb) on a ~30–60s
  interval, by design, to avoid running a supervised long-lived process in production.

## License

Academic capstone project — not licensed for public or commercial redistribution unless
stated otherwise by the project team.