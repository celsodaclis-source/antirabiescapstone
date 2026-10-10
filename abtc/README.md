# N-VISION — Anti-Rabies Vaccination Management System

A data-driven ticketing and vaccination management system for the
**Mandaluyong City Animal Bite Treatment Center (ABTC)**.

Built with **Laravel 12 (PHP 8+)**, **Blade + Tailwind CSS**, **MySQL/MariaDB**
(via Eloquent, with SQLite supported for quick local demos), **Laravel Breeze**
auth extended with **role-based access control**, and a **Semaphore SMS** wrapper.

## Modules

1. **Patient Registration & Records** — register patients, record bite incidents
   (animal type, exposure category I/II/III, site, animal status), search to avoid
   duplicates, full vaccination history.
2. **Ticket Queue Management** — auto-generated tickets (`A-001`…), Now-Serving +
   counter status panel, Call Next / Call Specific / Hold / Serve / Complete, and
   8-second polling so the view updates without a full refresh.
3. **Vaccination Scheduling & Dose Monitoring** — auto-schedules the WHO/DOH PEP
   protocol (Day 0 / 3 / 7 / 28), shows upcoming doses, reschedule, tracks
   administered vs. missed, and flags overdue doses.
4. **Vaccine Inventory Monitoring** — stock by batch/quantity/expiry, auto-deduct on
   dose administration (atomic transaction), low-stock and expiry warnings.
5. **Automated SMS Reminders** — editable message template with placeholders,
   manual + scheduled reminders via Semaphore, full SMS delivery log.
6. **Reporting Dashboard** — KPI cards, monthly patient-volume chart, and
   CSV / PDF export for LGU Health Office & DOH submission.

Every privileged action is written to an **audit log** (who did what), supporting the
Philippine Data Privacy Act of 2012 (RA 10173) accountability requirement.

## Roles

`Admin/IT Support`, `Doctor`, `Nurse`, `Administrative Staff`.
Only `Admin/IT Support` can manage users and system settings; only
`Admin/IT Support` and `Nurse` can manage inventory.

## Demo credentials (seeded)

| Role | Email | Password |
|------|-------|----------|
| Admin/IT Support | admin@abtc.gov.ph | password123 |
| Doctor | doctor@abtc.gov.ph | password123 |
| Nurse | nurse@abtc.gov.ph | password123 |
| Administrative Staff | staff@abtc.gov.ph | password123 |

## Running locally (no MySQL required)

The app defaults to a file-based **SQLite** database so it runs standalone.

```bash
cp .env.example .env          # already present; DB_CONNECTION=sqlite
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve              # http://127.0.0.1:8000
```

> Frontend assets use the Tailwind Play CDN, so **no `npm install` / build step is
> required** to run the demo. For an LGU/production build, run `npm install &&
> npm run build` and switch the layouts from the CDN `<script>` to
> `@vite(['resources/css/app.css','resources/js/app.js'])`.

## Deploying on an Apache/Nginx + MySQL LGU server

1. Set the DB connection in `.env`:
   ```dotenv
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=abtc
   DB_USERNAME=abtc_user
   DB_PASSWORD=********
   ```
2. `composer install --no-dev`
3. `php artisan migrate --seed` (or `php artisan migrate --force` in production)
4. `npm install && npm run build`
5. Point your web root at `public/` (set `APP_URL` and configure your virtual host).
6. Schedule reminders with cron:
   ```cron
   * * * * * cd /var/www/abtc && php artisan schedule:run >> /dev/null 2>&1
   ```
   (the `sms:send-reminders` command runs hourly).

## SMS (Semaphore)

Set in `.env` (swap providers by replacing `app/Services/SemaphoreSms.php`):

```dotenv
SEMAPHORE_SMS_API_KEY=your-key
SEMAPHORE_SMS_SENDER=N-VISION
SEMAPHORE_SMS_BASE_URL=https://api.semaphore.co/api/v4
SMS_REMINDER_LEAD_HOURS=24
```

Without an API key the system logs attempts as `failed` (never crashes), which is
correct for offline demoing.

## Running the tests

```bash
php artisan test
```
