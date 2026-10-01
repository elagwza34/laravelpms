# Laravel PMS — Foundation

Production-grade backend foundation for a **multi-tenant PMS**, built to run on
**Hostinger Business (shared hosting)** with **MySQL/MariaDB**, serving a
separate **React + TypeScript** frontend.

> **Status: foundation only.**
> The application boots, is tested and is deployment-ready. No PMS business
> modules (Products, Inventory, Sales, Purchases, Customers, Suppliers, POS,
> Reports, WooCommerce) have been implemented, by design.

---

## 1. Environment

| Component  | Version                     | Notes                                   |
|------------|-----------------------------|-----------------------------------------|
| PHP        | 8.2.12 (ZTS, x64)          | From XAMPP (`C:\xampp\php`)             |
| Laravel    | 12.69.3                     | Requires PHP ^8.2                       |
| Composer   | 2.10.3                      | Installed at `C:\xampp\composer`        |
| Node.js    | 24.19.0                     | Ready for the future React frontend     |
| npm        | 11.17.0                     |                                         |
| Database   | MariaDB 10.4.32             | `utf8mb4_unicode_ci`                    |
| PHPUnit    | 11.5                        | Runs on isolated in-memory SQLite       |
| Pint       | 1.13                        | `laravel` preset                        |

### Why Laravel 12 and not 13?

Laravel 13 requires **PHP 8.3+**. This machine runs **PHP 8.2.12**, so Laravel 12
is the newest release fully compatible here. Laravel 12 supports PHP 8.2-8.5 and
receives **security fixes until 24 Feb 2027**.

---

## 2. Project structure

```
app/
├── Http/
│   ├── Controllers/Api/V1/HealthController.php   # health / readiness probe
│   ├── Middleware/
│   │   ├── ForceJsonResponse.php                # API always returns JSON
│   │   └── ResolveTenant.php                    # publishes active tenant
│   └── Requests/Api/ApiFormRequest.php          # base validation contract
├── Models/User.php                              # + Sanctum HasApiTokens
├── Providers/
│   ├── AppServiceProvider.php                   # rate limiters
│   └── TenancyServiceProvider.php               # binds TenancyContext
└── Support/Tenancy/
    ├── Contracts/TenantResolver.php             # tenant resolution contract
    └── TenancyContext.php                       # per-request tenant holder

bootstrap/app.php        # API routing, middleware stack, JSON error handling
config/
├── cors.php             # CORS driven by API_ALLOWED_ORIGINS
└── tenancy.php          # multi-tenancy seam (disabled by default)
routes/
├── api.php              # /api/v1/*
└── web.php
tests/Feature/           # health, error handling, CORS, tenancy
```

---

## 3. What is configured

### Database
`DB_CONNECTION=mysql`. Sessions, cache and queues use the **database** driver so
shared hosting works without any extra services. **Redis is not required.**

Local databases created:

- `laravel_pms` — development
- `laravel_pms_testing` — reserved

### Authentication
`laravel/sanctum` ^4.3 installed; `User` uses `HasApiTokens`. Both **bearer
token** and **SPA cookie** flows are supported. The API rate limiter is keyed by
user id, falling back to client IP.

### API
- Versioned routing under `/api/v1`
- Unified JSON error envelope: `{ "message", "error_code", "errors" }`
- Handled cases: validation (422), unauthenticated (401), resource missing (404),
  endpoint missing (404), other HTTP errors, unhandled 500s
- Internal exceptions are logged via `report()` and never leaked to the client
- `ForceJsonResponse` guarantees no HTML error page ever reaches the API

### CORS
Driven by `API_ALLOWED_ORIGINS` (comma-separated, defaults to the Vite dev
servers on port 5173). `HandleCors` runs first in the API stack so preflight
requests are never rejected by authentication.

### Multi-tenancy (architecture only)
Prepared, **not** active:

- `PMS_TENANCY_ENABLED=false` — requests stay single-tenant
- `TenancyContext` is a **scoped** singleton and is always cleared after the
  response, so a tenant can never leak between requests or queue jobs
- `TenantResolver` is a **contract with no binding**. How a tenant is identified
  (subdomain, header, token claim) is an unmade business decision — implementing
  it later is a single `bind()` in `TenancyServiceProvider`
- `config/tenancy.php` reserves `tenant_id` as the future scoping column

### Logging & production readiness
`config:cache` verified to work. `composer audit` reports **no security
advisories**.

---

## 4. Usage

```bash
php artisan serve
php artisan test
php vendor/bin/pint
```

Health endpoints:

```bash
curl http://127.0.0.1:8000/api/v1/health
curl http://127.0.0.1:8000/up
```

---

## 5. Deployment notes (Hostinger)

1. Set `APP_ENV=production`, `APP_DEBUG=false`, real `APP_URL`
2. `php artisan key:generate`
3. `php artisan config:cache && php artisan route:cache && php artisan view:cache`
4. Point the domain's document root to `public/`
5. Set `API_ALLOWED_ORIGINS` to the real frontend domain
6. Configure `trustProxies(at: [...])` in `bootstrap/app.php` if Hostinger sits
   behind a proxy
7. Run `php artisan queue:work --daemon` (or cron) for queued jobs

---

## 6. Known environment issues

- **PHP was not on `PATH`.** `C:\xampp\php`, `C:\xampp\composer` and
  `C:\xampp\mysql\bin` were added to the user `PATH`.
- **Missing PHP extensions.** `zip`, `intl`, `gd` and `sodium` were disabled in
  `php.ini` and have been enabled; `exif` was loaded twice and de-duplicated;
  `opcache` enabled. Backup of the original file:
  `C:\xampp\php\php.ini.backup-foundation`.
- **`artisan db:show` fails on MariaDB** (`performance_schema.session_status`
  missing). This is a known XAMPP/MariaDB quirk in that command's diagnostics,
  not an application problem — the database itself works correctly.