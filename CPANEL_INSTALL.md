# WhatsApp Commerce for SMEs cPanel + MySQL Installation Guide

This build includes the WhatsApp Commerce for SMEs frontend and API. It is designed to run on standard cPanel hosting using **MySQL**, PHP, database queues, cron jobs, and Apache/LiteSpeed. Redis and Supervisor are optional.

## 1. Hosting requirements

Recommended minimum:

- PHP 8.2 or newer
- MySQL 8.0+ or MariaDB 10.6+
- Composer 2.x
- HTTPS/SSL certificate
- cPanel Cron Jobs
- PHP extensions: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `pdo`, `pdo_mysql`, `session`, `tokenizer`, `xml`

Optional but useful: `redis`, `intl`, `zip`.

## 2. Create the MySQL database

In cPanel → **MySQL Databases**:

1. Create a database, e.g. `cpaneluser_wc`.
2. Create a database user.
3. Add the user to the database with **ALL PRIVILEGES**.
4. Save the full cPanel-prefixed database and username values.

## 3. Upload the Laravel application

Recommended layout:

```text
/home/CPANEL_USER/
├── whatsapp-commerce/       # entire Laravel frontend/API project
└── public_html/
    └── api/                 # only Laravel public/ files if subdomain root cannot be changed
```

### Best option: subdomain with custom document root

Create a subdomain such as `wc.vigourtech.net` and set its document root to:

```text
/home/CPANEL_USER/whatsapp-commerce/public
```

This is the safest and cleanest deployment.

### Alternative: shared-hosting public_html layout

If cPanel does not allow the document root to point to Laravel's `public` folder:

1. Keep the Laravel app outside `public_html`, e.g. `/home/USER/whatsapp-commerce`.
2. Copy the contents of Laravel's `public/` into `/home/USER/public_html/api/`.
3. Edit `/home/USER/public_html/api/index.php` paths so they point back to the Laravel project:

```php
require __DIR__.'/../../../whatsapp-commerce/vendor/autoload.php';
$app = require_once __DIR__.'/../../../whatsapp-commerce/bootstrap/app.php';
```

Adjust the number of `../` segments for the actual directory structure.

Never place `.env`, `vendor` internals, `storage`, or application source code directly in a publicly browsable directory when avoidable.

## 4. Configure `.env`

Copy:

```bash
cp .env.example .env
```

Then set:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://wc.vigourtech.net

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=cpaneluser_wc
DB_USERNAME=cpaneluser_wcapi
DB_PASSWORD=YOUR_STRONG_DB_PASSWORD

CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=database
```

Add the Meta WhatsApp credentials and payment provider settings.

## 5. Install dependencies

Using cPanel Terminal or SSH:

```bash
cd ~/whatsapp-commerce
composer install --no-dev --optimize-autoloader
php artisan key:generate --force
php artisan migrate --force
php artisan storage:link
php artisan optimize
```

If Composer is unavailable on the server, run `composer install --no-dev --optimize-autoloader` locally using the same PHP major/minor version, then upload the generated `vendor/` directory.

## 6. Permissions

Laravel must be able to write to:

```text
storage/
bootstrap/cache/
```

Typical cPanel permissions are directories `755` or `775`, depending on the host. Avoid `777`.

## 7. Queue processing on cPanel

This build defaults to the **database queue**, which avoids requiring Redis.

### Preferred when cPanel supports a long-running process

```bash
php artisan queue:work database --sleep=2 --tries=5 --timeout=60
```

### Standard shared-cPanel approach: cron

Create a cPanel Cron Job running every minute:

```cron
* * * * * cd /home/CPANEL_USER/whatsapp-commerce && /usr/local/bin/php artisan queue:work database --stop-when-empty --tries=5 --timeout=60 >> /dev/null 2>&1
```

Some hosts expose PHP at another path such as:

```text
/usr/local/bin/ea-php84
```

Use `which php` in Terminal to confirm.

This starts a short worker each minute, drains queued jobs, and exits. It is appropriate for an MVP/low-to-moderate traffic deployment. For high-volume WhatsApp traffic, move queue workers to a VPS or managed service with Supervisor/systemd and preferably Redis.

## 8. Laravel scheduler cron

Add another every-minute cron entry:

```cron
* * * * * cd /home/CPANEL_USER/whatsapp-commerce && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

## 9. WhatsApp webhook

Configure Meta to use:

```text
https://wc.vigourtech.net/api/webhooks/whatsapp
```

Use the exact value of `WHATSAPP_WEBHOOK_VERIFY_TOKEN` as the Meta verification token.

Make sure HTTPS is active and the endpoint is publicly reachable.

## 10. Production commands after each deployment

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
php artisan queue:restart
```

If using short cron workers, `queue:restart` is not essential but is safe.

## 11. Security checklist

- `APP_DEBUG=false`
- Force HTTPS
- Keep `.env` outside public web root
- Use a strong `APP_KEY`
- Use a strong MySQL password
- Keep `WHATSAPP_ENFORCE_SIGNATURE=true`
- Do not expose access tokens in API responses/logs
- Back up MySQL daily
- Use Meta system-user/permanent credentials appropriate for production
- Restrict cPanel/SSH accounts with MFA where supported
- Keep Laravel/PHP dependencies patched

## 12. Scaling recommendation

cPanel + MySQL is a good launch platform for dozens to a few hundred merchants when message volume is moderate. The first bottleneck is normally queue/background execution rather than MySQL itself.

When WhatsApp volume grows, retain Laravel + MySQL and move only the runtime to a VPS/cloud setup with:

- Nginx
- PHP-FPM
- Redis
- Supervisor/systemd
- multiple queue workers

The application architecture does not need to be rewritten for that migration.
