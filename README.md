# TeaCare — Office Tea, Coffee & Guest Refreshment PWA

**TeaCare** is a fast, mobile-first Progressive Web App (PWA) for managing daily office tea/coffee sessions, urgent on-demand requests, and meeting/guest orders across departments and rooms. Built with **Plain PHP 8.2**, **MySQL / MariaDB**, **Tailwind CSS** (precompiled for instant zero-Node hosting), **Minimal Vanilla JS**, and **Web Push (VAPID) + WhatsApp (Evolution API)** notifications.

---

## Key Features

### Employee Portal (Mobile-First PWA & Desktop)
- **Daily Regular Sessions (Morning & Afternoon):** Automatically generates regular orders at configured session cutoffs from each employee's personal defaults, with one-tap **Change Today**, **Skip Today**, or **Reset to Default**.
- **On-Demand Orders:** Request tea or coffee anytime with beverage, sugar level (`Normal`, `Less`, `No Sugar`), quantity, room, and custom notes—without altering recurring defaults.
- **Guest & Meeting Orders:** Authorized employees/managers can request mixed multi-variation tea & coffee trays for meeting rooms with guest count and approximate delivery timing (`Immediate`, `After 5 min`, `After 10 min`, `After 15 min`, `After 30 min`, `After 45 min`, `After 1 hour`).
- **Personal Cup Recognition:** Employees can upload a personal mug/cup photo (automatically resized & compressed into clean WebP/JPEG thumbnails) with an inline `(i)` square `500×500 px` guide tooltip so tea staff never mix up personal cups.
- **Real-Time Status & Confirmation:** Live order badges (`Pending`, `Preparing...`, `Almost Done (~Xm)`, `Delivered`, `Cancelled`, `Auto Cancel`) plus confirmation when orders arrive.
- **Playful Tea Monkey Companion:** Optional, lightweight draggable SVG companion featuring 18 rotating tea/coffee animations every 2 minutes for 30 seconds; employees can close it for the day (`×`) or toggle it permanently ON/OFF in Settings.
- **Clean History Filters:** Mobile-friendly `Today`, `7 Days`, `30 Days`, `Older` tabs with requested/delivered/cancelled summaries and paginated history.

### Tea Staff Operations Dashboard
- **Real-Time Preparation Queue:** Separate tabs for **Skip List** (who skipped/paused today, filterable by room/department), **Morning**, **Afternoon**, **Urgent & Guest**, and **Completed**.
- **Batch & Room Delivery Actions:** Mark individual orders (`Accept`, `Preparing`, `Almost Done` with ETA `1–30 min`, `Delivered`) or deliver an entire room/session batch in one click.
- **Staff Announcements:** Post instant **Unavailable Today** or **Serving Time Adjusted** banners that appear live at the top of employee dashboards.
- **Web Push & WhatsApp Alerts:** Instant browser push alerts for urgent/guest orders and optional WhatsApp group notifications via Evolution API.

### Admin Control Panel
- **KPI Dashboard:** Active employees, pending approvals, morning/afternoon cup counts, 7-day beverage trend chart, and live Today by Department / Today by Room breakdowns.
- **Complete Directory Management:** Approve registrations, manage employees/staff/admins, departments, and office/meeting rooms.
- **Order Audits & Corrections:** Filter historical orders by date/type/status/room/department, inspect order event timelines, and apply audited status corrections.
- **Reports & CSV Export:** Date-range analytics with Daily Breakdown, Department Summary, and one-click CSV download.
- **High-Performance Server-Side Pagination (`25 / 50 / 100 / 500`):** Every admin table uses indexed SQL `COUNT` + `LIMIT / OFFSET` pagination with batch item loading (`IN (...)`) for zero lag even on large datasets.
- **Schedule, Holidays & Notification Logs:** Configure cutoff/service times, working days, holidays, Web Push delivery logs, WhatsApp queue retries, and full Audit Logs.

### Automation & Reliability
- **Idempotent Cutoff Finalizer:** Generates regular orders cleanly at session cutoff times with duplicate protection (`regular_unique_key`).
- **3-Hour System Auto-Cancel:** Orders left unaccepted for >3 hours (or accepted/preparing without delivery for >3 hours after acceptance) automatically transition to `system_cancelled` (`Auto Cancel`) with audit trail entries.

---

## Option A: Installing on cPanel (Shared / VPS Hosting)

> Pre-compiled CSS (`public/assets/css/app.css`) and Tabler Icons (`public/assets/tabler/`) are already included in this repository—**you do NOT need Node.js or npm on cPanel.**

### 1. Server Requirements
- **PHP 8.2+** with extensions: `pdo_mysql`, `gd` (with JPEG/PNG/WebP), `gmp`, `openssl`, `mbstring`, `curl`, `json`
- **MySQL 8.0+** or **MariaDB 10.6+**
- **Apache / LiteSpeed** with `mod_rewrite` enabled (standard on cPanel)
- **Composer** (available in cPanel Terminal on most hosts, or upload `vendor/` locally via ZIP)

### 2. Create MySQL Database in cPanel
1. Open **cPanel -> MySQL Databases**.
2. Create a new database (e.g., `cpaneluser_teacare`).
3. Create a new MySQL user with a strong password and **Add User to Database** with **All Privileges**.

### 3. Upload / Clone Project Files
In cPanel **Terminal** (recommended) or **Git Version Control**:
```bash
cd ~
git clone https://github.com/dev-talha/tea-care.git tea-care
cd tea-care
```
If installing Composer dependencies on cPanel Terminal:
```bash
composer install --no-dev --optimize-autoloader
```
*(If Composer is not installed on your cPanel plan, run `composer install --no-dev --optimize-autoloader` on your local machine and upload the `vendor/` folder into `~/tea-care/vendor/`.)*

### 4. Configure Environment (`.env`)
Copy `.env.example` to `.env` and fill in your real details:
```bash
cp .env.example .env
nano .env
```
Update these values carefully:
- `APP_URL=https://yourdomain.com` (HTTPS is required for PWA & Web Push notifications)
- `APP_ENV=production`
- `APP_SECRET=` generate a random 64-char string (`php -r "echo bin2hex(random_bytes(32));"`)
- `DB_HOST=127.0.0.1` (or `localhost` on some cPanel hosts)
- `DB_DATABASE=your_cpanel_db_name`
- `DB_USERNAME=your_cpanel_db_user`
- `DB_PASSWORD=your_strong_db_password`
- `VAPID_SUBJECT=mailto:admin@yourdomain.com`
- `DEFAULT_ADMIN_PASSWORD=` set a strong password for initial `ADMIN001` setup
- `SHOW_DEMO_LOGINS=false`

> Never commit `.env` to Git. Root and public `.htaccess` files already block HTTP access to `.env` and internal directories.

### 5. Set File Permissions & Run Setup
Ensure upload and log folders are writable:
```bash
chmod -R 755 storage
```
Run database schema migration, seed initial departments/rooms/accounts, and generate Web Push VAPID keys:
```bash
php bin/migrate.php
php bin/generate-vapid.php
```
*Note: If your cPanel plan does not allow Terminal access, you may import `database/schema.sql` and `database/seed.sql` via **cPanel -> phpMyAdmin**, then trigger `bin/migrate.php` once via a temporary Cron Job to create initial user accounts.*

### 6. Configure Domain / Document Root
Choose **either** method:
1. **Recommended (Addon Domain / Subdomain):** Point the domain's **Document Root** directly to `/home/YOUR_CPANEL_USER/tea-care/public`. `public/.htaccess` handles clean URLs and security headers automatically.
2. **Installing inside `public_html`:** Upload/move the contents of `tea-care` directly into `public_html/` (or `public_html/teacare/`). The included root `.htaccess` protects `src/`, `bin/`, `database/`, `storage/`, `vendor/`, and `.env` while forwarding traffic cleanly into `public/`.

### 7. Set Up cPanel Cron Job (Required)
TeaCare needs a periodic worker to finalize morning/afternoon cutoffs, auto-cancel stale 3-hour orders, and deliver queued push/WhatsApp notifications.

Open **cPanel -> Cron Jobs**, choose **Once Per Minute (`* * * * *`)**, and add:
```bash
* * * * * /usr/local/bin/php /home/YOUR_CPANEL_USER/tea-care/bin/cron.php >/dev/null 2>&1
```
*(Check your PHP 8.2 binary path in cPanel, e.g., `/opt/cpanel/ea-php82/root/usr/bin/php` or `/usr/local/bin/php`. Do **not** run `bin/scheduler.php` in cPanel Cron Jobs—`bin/cron.php` runs a single pass with a file lock and exits cleanly.)*

---

## Option B: Installing with Docker

```bash
cp .env.example .env
# Edit .env with DB credentials, APP_URL, APP_SECRET, and DEFAULT_ADMIN_PASSWORD
docker compose up -d --build
```
`bin/migrate.php` and `bin/generate-vapid.php` run automatically when `teacare_app` starts, and `teacare_scheduler` runs background jobs continuously.

---

## Automated Tests & Maintenance

Run the built-in automated acceptance test suite:
```bash
php tests/run_tests.php
```

Database backup & restore:
```bash
# Backup
mysqldump -u YOUR_DB_USER -p YOUR_DB_NAME > teacare-backup.sql

# Restore
mysql -u YOUR_DB_USER -p YOUR_DB_NAME < teacare-backup.sql
```

## Security Checklist Before Going Live
- Use **HTTPS (SSL/TLS)** via AutoSSL / Let's Encrypt on cPanel (required for Service Worker & Web Push).
- Change passwords for any seeded sample accounts (`ADMIN001`, `STAFF001`, `EMP001`, `EMP002`) or remove unused sample accounts after logging in.
- Verify `https://yourdomain.com/.env` returns **403/404**.
- Keep `APP_ENV=production` and `SHOW_DEMO_LOGINS=false` in `.env`.
