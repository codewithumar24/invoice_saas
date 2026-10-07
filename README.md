<div align="center">

# Signalbook

**Multi-tenant invoicing and business management SaaS**

Create invoices, accept payments, track inventory and expenses, and run reports, all from one workspace per business.

![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-06B6D4?logo=tailwindcss&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white)

</div>

---

## Table of Contents

- [Features](#features)
- [Tech Stack](#tech-stack)
- [Requirements](#requirements)
- [Installation](#installation)
- [Default Logins](#default-logins)
- [Background Work](#background-work)
- [Configuration](#configuration)
- [Project Structure](#project-structure)
- [Security and Multi-Tenancy](#security-and-multi-tenancy)
- [REST API](#rest-api)
- [Testing](#testing)
- [Known Limits](#known-limits)
- [License](#license)

## Features

- **Multi-tenant workspaces**: every business gets an isolated workspace with its own data.
- **Invoicing**: invoices, recurring invoices, overdue flagging and automatic payment reminders.
- **Payments**: Stripe, PayPal and manual payment methods, configured per workspace.
- **Business management**: customers, products, inventory, expenses and reports.
- **Customer portal**: a separate area where customers can view their documents.
- **Platform admin panel** (`/admin`): plans, limits, feature flags, announcements, legal pages and maintenance mode.
- **Subscription billing**: workspaces pay the platform through Stripe.
- **REST API** (`/api/v1`): token-based access to customers, invoices, payments, products and expenses.
- **Automation and operations**: scheduler tasks, health snapshots, backups and monitoring.
- **Localization**: interface wrapped in `__()`, with starter Urdu and Arabic translations and automatic RTL layout.

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13, PHP 8.3+ |
| Frontend | Blade, Tailwind CSS, vanilla JavaScript |
| Database | MySQL 8 / MariaDB 10.6+ (SQLite for tests) |
| Queue and cache | Redis (recommended for production) |
| Payments | Stripe, PayPal, manual methods |

## Requirements

- PHP 8.3+ with extensions: `pdo_mysql`, `mbstring`, `openssl`, `gd`, `zip`, `xml`, `bcmath`, `fileinfo`
- Composer 2
- Node.js 20+ and npm
- MySQL 8 (or MariaDB 10.6+)
- Redis (recommended for production, optional locally)

## Installation

**1. Install dependencies and create the environment file**

```bash
composer install
cp .env.example .env          # Windows: copy .env.example .env
php artisan key:generate
```

**2. Create an empty MySQL database** (for example `invoice_saas`) and set the `DB_*` values in `.env`.

**3. Set up the database, assets and server**

```bash
php artisan migrate --seed    # tables, plans, permissions, help articles, platform admin
php artisan db:seed --class=DemoSeeder   # optional: demo workspace with sample data
php artisan storage:link
npm install
npm run build                 # or: npm run dev while developing
php artisan serve
```

Open <http://localhost:8000>.

## Default Logins

After seeding:

| Who | Email | Password | URL |
|---|---|---|---|
| Platform admin | `admin@example.com` | `Admin@12345` | `/admin` |
| Demo workspace owner (DemoSeeder) | `demo@example.com` | `Demo@12345` | `/login` |

> [!WARNING]
> Change both passwords before going live. You can create another platform admin with `php artisan saas:super-admin`.

## Background Work

The default `.env` uses `QUEUE_CONNECTION=sync`, so emails, exports and imports run immediately and no worker is needed.

### Production queue setup

```env
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
```

Run a worker:

```bash
php artisan queue:work redis --queue=emails,invoices,notifications,webhooks,default,exports,imports,reports,analytics,maintenance
```

Or use Horizon (Linux/macOS only, requires the `pcntl` extension, so it is not installed by default):

```bash
composer require laravel/horizon
php artisan horizon
```

`config/horizon.php` is already included and `/horizon` is limited to platform admins.

### Scheduler (cron)

Recurring invoices, overdue flags, payment reminders, subscription checks, health snapshots and backups run from the scheduler. Add one cron entry:

```cron
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

Locally, use `php artisan schedule:work`. Tasks can also be run by hand:

```bash
php artisan saas:run
php artisan saas:subscriptions
php artisan saas:backup
php artisan saas:monitor
```

## Configuration

| Area | How to configure |
|---|---|
| **Mail** | Set `MAIL_*` in `.env`. With `MAIL_MAILER=log`, emails are written to `storage/logs/laravel.log`. |
| **Platform billing** (workspaces paying you) | Set `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`. Webhook URL: `/webhooks/billing/stripe` |
| **Customer payments** (customers paying a workspace) | Each workspace adds its own Stripe / PayPal / manual methods under **Settings → Payment methods**. Webhook URL: `/webhooks/payments/{gateway}/{workspace-slug}` |
| **Plans, limits, feature flags, announcements, legal pages, maintenance mode** | Managed in `/admin` |
| **Platform defaults** | `config/saas.php` |
| **Permissions** | `config/permissions.php` (run `php artisan db:seed` again after changing it) |

## Project Structure

```
app/Http/Controllers   thin controllers (web, portal, admin, API)
app/Http/Requests      validation
app/Services           business logic (invoices, payments, inventory, reports, health, automation ...)
app/Models             Eloquent models; tenant models extend TenantModel
app/Support            TenantContext, Money, helpers
app/Jobs, app/Events, app/Listeners
resources/views        Blade pages and components
resources/js           vanilla JS modules
routes/web.php, api.php, console.php
```

## Security and Multi-Tenancy

- Every tenant table has a `tenant_id`. The `BelongsToTenant` global scope filters every query and returns nothing when no workspace is set.
- Invoice totals are always calculated on the server (`InvoiceCalculator`); values sent by the browser are ignored.
- Webhooks are covered by signature and idempotency tests.

## REST API

Base path: `/api/v1`

Authenticate with a bearer token created under **Settings → API tokens**.

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Accept: application/json" \
     http://localhost:8000/api/v1/invoices
```

Available resources: customers, invoices, payments, products, expenses.

Responses use a consistent format:

```json
{
  "success": true,
  "message": "...",
  "data": {},
  "meta": {}
}
```

## Testing

```bash
php vendor/bin/phpunit
```

Tests run on an in-memory SQLite database (see `phpunit.xml`), so the `pdo_sqlite` extension is required.

## Known Limits

- **Payments:** Stripe and PayPal integrations follow their public APIs and are covered by signature/idempotency tests with simulated events, but they have not been run against live accounts. Test with sandbox keys before taking real payments.
- **Database:** Developed and tested on SQLite. Migrations use standard MySQL-compatible column types, but run the full test suite against your MySQL server once.
- **Redis:** Queue and cache settings are standard Laravel configuration and were not exercised against a Redis server.
- **QR codes:** They appear on web pages, not inside PDFs.
- **Legal pages:** Terms, privacy and refund pages are starter text and need review by a lawyer.
- **Translations:** The interface is fully wrapped in `__()`. `lang/ur.json` and `lang/ar.json` contain a starter set of terms, and RTL layout switches automatically.
- **Not implemented:** SMS and browser push channels, receipt OCR and the affiliate programme. Notification channels are in-app and email.

## License

Add your license here (for example MIT) and include a matching `LICENSE` file in the repository.