# Deploying PathwayTT

Target: a normal shared-hosting LAMP account (cPanel / Hostinger style) with PHP 8.3, MySQL
8, Composer and cron. Nothing in the app needs Docker, Redis, Node at runtime, or a
persistent worker process. The same steps apply to a VPS, with the queue worker as the one
optional upgrade.

## 1. Server requirements

- PHP **8.3** with extensions: `pdo_mysql mbstring intl zip gd exif openssl curl fileinfo dom xml`
- `memory_limit` ≥ 256M (512M recommended for resume parsing), `upload_max_filesize` ≥ 10M,
  `post_max_size` ≥ 12M — set in cPanel's *MultiPHP INI Editor* if needed
- MySQL 8 (or MariaDB 10.6+) database and user
- Composer available over SSH (or run `composer install` locally and upload `vendor/`)
- One cron job

## 2. Build locally

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

Commit or upload the result of `npm run build` (`public/build`) — production does not need
Node.

## 3. Directory layout on shared hosting

Laravel's web root must be `public/`, not the project root. Two workable layouts:

**A. Subdomain / addon domain pointed at `public` (preferred).** Upload the project to
`~/pathwaytt` and set the (sub)domain's document root to `~/pathwaytt/public` in cPanel.

**B. Primary domain with fixed `public_html`.** Upload the project to `~/pathwaytt`, then
copy the *contents* of `public/` into `~/public_html` and edit `public_html/index.php` so
the two `require` lines point at `../pathwaytt/vendor/autoload.php` and
`../pathwaytt/bootstrap/app.php`. Keep `storage/` and `.env` outside `public_html`.

Resumes are stored under `storage/app/private` and served only through a signed,
policy-checked route — with either layout they are never web-accessible.

## 4. Environment

```bash
cp .env.example .env
php artisan key:generate
```

Set at minimum:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.tt
DB_*                     # your MySQL credentials
MAIL_*                   # SMTP for verification emails (host's mail server is fine)
QUEUE_CONNECTION=database
QUEUE_VIA_SCHEDULER=true # shared hosting: no daemon, cron drains the queue
JOBSOURCE_REMOTIVE=true  # etc. — see .env.example
```

`APP_ENV=production` disables the demo user and demo job seeders.

## 5. Database and reference data

```bash
php artisan migrate --force
php artisan db:seed --force      # industries, skills, learning resources, settings (idempotent)
php artisan storage:link         # harmless; only the public disk uses it
php artisan optimize              # config/route/view caches
```

Create the first admin:

```bash
php artisan tinker
>>> $u = App\Models\User::create(['name' => 'Admin', 'email' => 'you@example.tt', 'password' => 'choose-a-strong-password']);
>>> $u->forceFill(['is_admin' => true, 'email_verified_at' => now()])->save();
```

Then set the real TTD/USD rate under **Admin → Settings → Matching & FX** (the seeded 6.80
is a placeholder).

## 6. Cron (the whole background system)

One entry, every minute:

```
* * * * * cd /home/USER/pathwaytt && php artisan schedule:run >> /dev/null 2>&1
```

This drives:

- the **queue** (resume parsing, match recomputation, gap plans) — each minute
  two background workers start (`--queue=default` for parsing/matching/plans, `--queue=sync`
  for board crawls and the post-sync fan-out), each exiting when empty or after 50s, so a
  ten-minute crawl never delays a resume parse;
- the hourly **job:sync** that keeps the live feed current (each board adapter
  respects its own rate limit, so hourly never over-calls a board).

If the host's cron uses a different PHP binary, use its full path (e.g.
`/usr/local/bin/php83`).

## 7. Re-verify after deploying

- `https://your-domain.tt/up` returns 200.
- Register a test account; the verification email arrives.
- Upload a resume; within a minute the profile review screen fills in (cron is working).
- **Admin → Jobs → Job sync → Run sync now** imports listings and the run rows show notes,
  not errors. (If you see `cURL error 60`, PHP lacks a CA bundle — ask the host, or set
  `curl.cainfo` in the PHP INI editor.)

## 8. Updating

```bash
git pull            # or upload the new files
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --force      # reference data only; never touches user data
php artisan optimize:clear && php artisan optimize
```

Upload the new `public/build` if assets changed.

## 9. VPS notes

Everything above applies. Additionally you may:

- set `QUEUE_VIA_SCHEDULER=false` and run `php artisan queue:work --tries=3` under
  Supervisor for lower latency;
- put Nginx/Apache in front with the document root at `public/` (the Docker
  `docker/apache.conf` is a reference vhost);
- use Redis for cache/session/queue by changing the corresponding `*_CONNECTION` values —
  no code changes required.

## 10. Backups and privacy

Back up the database **and** `storage/app/private` (resumes). Both are personal data:
users can delete their resume or their whole account from the app, and both actions
hard-delete the files. Keep backups on the same retention terms.
