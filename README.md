<p align="center">
  <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="300" alt="Laravel Logo">
</p>

# Close-Out Package — pm-server

Monolithic SPA built with **Laravel 12 + Inertia.js v2 + React 18** (Breeze stack).
Generates Verizon cell-site Close-Out Packages (COP) — see `docs/` at the workspace root for the full product breakdown.

> **Architecture:** Modular Monolith (`app/Domains/*`, `app/Shared/*`). Auth is handled by an **external SSO** — there are no local login/register routes.

---

## 1. Requirements

| Tool | Version |
|---|---|
| PHP | **8.2+** (platform constraint — do not bump packages that need ^8.3) |
| Composer | 2.x |
| Node.js | 18+ (Vite 7) |
| MySQL | 8.x |
| Redis | 6+ (session/queue/cache) |
| PHP extensions | `pdo_mysql`, `redis` (or run `composer require predis/predis` and set `REDIS_CLIENT=predis`), `gd` or `imagick`, `exif` |
| Chromium/Chrome | required for PDF generation (Browsershot) — set `CHROME_PATH` |

---

## 2. First-time setup

```bash
# 1. Install PHP + JS dependencies
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Configure .env — at minimum:
#    DB_CONNECTION=mysql, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
#    SESSION_DRIVER=redis, QUEUE_CONNECTION=redis, CACHE_STORE=redis
#    REDIS_HOST, REDIS_PORT, REDIS_CLIENT=phpredis
#    SSO_AUTHORIZATION_URL, SSO_LOGOUT_URL
#    CHROME_PATH=/usr/bin/chromium (if not on PATH)

# 4. Database + seeders
php artisan migrate
php artisan db:seed

# 5. One-time package setup (migrations/config that are not yet published)
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-config"
php artisan horizon:install
php artisan pest:install

# 6. Frontend
npm run build

# 7. Verify the app
php artisan serve   # http://localhost:8000
```

---

## 3. Daily development

```bash
# Run everything (server + queue + live logs + Vite) with one command
composer dev

# Or run pieces individually
php artisan serve            # HTTP server
php artisan queue:listen     # queue worker (use: php artisan horizon)
php artisan pail             # live log tail
npm run dev                  # Vite dev server
```

---

## 4. Quality gates (run before every commit / PR)

```bash
# Fix code style automatically (Laravel preset)
composer format

# Check code style (fails on violations)
composer lint

# Static analysis — PHPStan via Larastan, level max (zero errors required)
composer analyse

# Run the full test suite (PHPUnit/Pest)
composer test

# Everything in one go
composer quality
```

| Command | What it does | Required to pass |
|---|---|---|
| `composer lint` | `vendor/bin/pint --test` | ✔ no style violations |
| `composer analyse` | `vendor/bin/phpstan analyse` (level max) | ✔ 0 errors |
| `composer test` | `php artisan test` | ✔ all green |
| `composer quality` | lint + analyse + test | ✔ all of the above |

> **CI rule:** merge only when `composer quality` passes and SonarQube's quality gate is green (0 bugs, 0 vulnerabilities, 0 code smells on new code).

---

## 5. Project structure

```
app/
├── Domains/          # Modular Monolith: Users, Projects, Sites, Templates,
│                     # CloseoutPackages, Photos, Approval, Dashboard
│   └── <Domain>/     # Actions/, Controllers/, DTOs/, Enums/, Models/,
│                     # Policies/, Requests/, Services/
├── Shared/           # Actions, DTOs, Enums, Traits, Helpers, Exceptions
├── Http/             # abstract base Controller + Middleware only
├── Console/
└── Providers/
```

**Controllers, FormRequests, Models, and Policies live inside their domain** (e.g. `app/Domains/Users/Controllers/SsoAuthController.php`) — never in global folders.

**Agent rules** (read before coding): `../.agents/rules/pm-server.md` + companions
(`pm-server-init.md`, `pm-server-inertia.md`, `pm-server-domain.md`, `pm-server-testing.md`, `pm-server-sonarqube.md`).

---

## 6. Useful commands

```bash
php artisan route:list                 # inspect routes
php artisan tinker                     # REPL
php artisan ide-helper:generate        # IDE autocomplete stubs
php artisan permission:cache-reset     # after role/permission changes
php artisan horizon                    # queue dashboard (Redis)
```

---

## 7. Troubleshooting

- **`phpredis` extension missing** → `composer require predis/predis` and set `REDIS_CLIENT=predis` in `.env`.
- **PDF generation fails** → install Chromium (`sudo apt install chromium-browser` or `npm i puppeteer`) and set `CHROME_PATH`.
- **`composer install` picks wrong package versions** → PHP must stay at 8.2+; several packages (Laravel 13, permission 8.x, activitylog 5.x, Pest 5.x) require PHP 8.3+ and are intentionally pinned to older majors.
- **Login redirects to blank SSO URL** → `SSO_AUTHORIZATION_URL` is empty in `.env`; the controller fails loudly by design.
