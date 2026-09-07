# Deploying PathwayTT to Hostinger shared hosting

Step-by-step for Hostinger's hPanel. The project lives **inside** `public_html`, in a
subfolder, and a root `.htaccess` routes every request into Laravel's `public/` folder.
The generic version (cPanel, VPS) is in [DEPLOYMENT.md](DEPLOYMENT.md).

This guide is written for the current install:

| Item           | Value                                                                       |
|----------------|-----------------------------------------------------------------------------|
| Site           | `blue-snake-221127.hostingersite.com`                                       |
| App folder     | `domains/blue-snake-221127.hostingersite.com/public_html/pathwaytt`         |
| App URL        | `https://blue-snake-221127.hostingersite.com/pathwaytt`                     |
| SSH user       | `u269010508`                                                                |
| PHP (CLI)      | `/opt/alt/php83/usr/bin/php` — the plain `php` command is 8.2 and will not run the app |

When you later move to a real domain, replace the site name throughout. If the app is
moved to the domain root instead of a subfolder, nothing in the bundle changes; only
`APP_URL` and the cron path do.

**Plan requirement:** Premium or Business shared hosting. Both include SSH, Composer and
cron. Hostinger has no Node, so front-end assets are compiled on your PC and shipped in the
zip.

## How the subfolder layout works

- The zip ships a root `.htaccess` (source: `scripts/hostinger.htaccess`) that rewrites
  every request into `public/`, returns **403** for direct requests to `.env`, `artisan`,
  `app/`, `config/`, `storage/`, `vendor/` and the other internals, and leaves
  `/.well-known/` alone for SSL validation.
- `public/index.php` detects this layout and tells Laravel its base URL is `/pathwaytt`,
  so routes, redirects, Livewire, Filament and asset links all carry the subfolder.
- Uploaded resumes live in `storage/app/private` and are served only through the app's
  signed, policy-checked route. They are never reachable by URL.

## 1. Build the upload bundle on your PC

```powershell
powershell -ExecutionPolicy Bypass -File scripts\build-hostinger.ps1
```

This compiles the Vite assets, installs production-only Composer packages into a staging
copy (your local `vendor/` is untouched), strips `.env`, logs, sessions, uploaded resumes
and tests, adds the root `.htaccess`, and writes `dist\pathwaytt-<version>.zip` (about
24 MB) with forward-slash paths so Linux extracts it correctly.

## 2. Prepare the hosting account (hPanel)

1. **Websites → Manage → Advanced → PHP Configuration.**
   - PHP version: **8.3**.
   - *PHP options* tab: `memory_limit` **512M**, `upload_max_filesize` **10M**,
     `post_max_size` **12M**, `max_execution_time` **120**.
   - *PHP extensions* tab: make sure `intl`, `gd`, `exif`, `zip`, `fileinfo`, `dom`,
     `mbstring`, `pdo_mysql`, `curl`, `openssl` are ticked (most are on by default).
2. **Databases → Management → Create new MySQL database.** Note the database name, user and
   password. Hostinger prefixes both name and user with `u269010508_`, e.g.
   `u269010508_pathwaytt`. The host is `localhost`.
3. **Emails.** Create a mailbox (used for verification and password-reset mail) and note
   its password. On the temporary `hostingersite.com` domain you may not be able to create
   one; in that case keep `MAIL_MAILER=log` for now and read verification links from
   `storage/logs/laravel.log`, or set `REQUIRE_EMAIL_VERIFICATION=false` (the default).
4. **Advanced → SSH Access.** Enable, and note host and port. Connect with PowerShell:
   `ssh -p PORT u269010508@HOST`.
5. **PHP on the command line.** The `php` command over SSH is 8.2, and the app needs 8.3.
   Every `php artisan` command in this guide therefore uses the full 8.3 binary path. At the
   start of each SSH session run:

   ```bash
   alias php=/opt/alt/php83/usr/bin/php
   php -v        # must print PHP 8.3.x
   ```

   If that path does not exist, find the 8.3 binary with `ls /opt/alt/ | grep php`,
   `ls /usr/local/bin /usr/bin | grep -i php` and use that path instead.

## 3. Upload and extract

1. **Files → File Manager**, open `domains/blue-snake-221127.hostingersite.com/public_html/`.
2. Create a folder named **`pathwaytt`** and open it.
3. Upload `pathwaytt-<version>.zip` into that folder.
4. Right-click the zip → **Extract** → extract **here** (into `pathwaytt` itself, not a
   further subfolder). You should end up with `pathwaytt/artisan`, `pathwaytt/public/`
   and `pathwaytt/.htaccess` (turn on *Show hidden files* to see it).
5. Delete the zip.

## 4. Configure the app

```bash
alias php=/opt/alt/php83/usr/bin/php
cd ~/domains/blue-snake-221127.hostingersite.com/public_html/pathwaytt
cp .env.example .env
nano .env
```

Change these lines (leave the rest as in the example):

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://blue-snake-221127.hostingersite.com/pathwaytt

DB_DATABASE=u269010508_pathwaytt
DB_USERNAME=u269010508_pathwaytt
DB_PASSWORD=the-database-password

MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=the-mailbox-address
MAIL_PASSWORD=the-mailbox-password
MAIL_FROM_ADDRESS="the-mailbox-address"

QUEUE_CONNECTION=database
QUEUE_VIA_SCHEDULER=true
```

`APP_URL` **must** include `/pathwaytt`: it is what emails and queued jobs use to build
links. Optional: `RESUME_PARSER_DRIVER=llm` plus `ANTHROPIC_API_KEY=` for LLM resume
parsing; `JOBSOURCE_*` toggles for which boards to crawl. Save with `Ctrl+O`, `Enter`,
`Ctrl+X`.

Then:

```bash
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force        # industries, skills, learning resources, settings
php artisan storage:link
chmod -R 775 storage bootstrap/cache
php artisan optimize
php artisan mail:test you@example.com   # confirm SMTP works (skip if MAIL_MAILER=log)
```

Create the first admin (edit the email and password first, then paste as one command):

```bash
php artisan tinker --execute="\$u = App\Models\User::create(['name' => 'Admin', 'email' => 'you@example.com', 'password' => 'choose-a-strong-password']); \$u->forceFill(['is_admin' => true, 'email_verified_at' => now()])->save(); echo 'Admin created';"
```

Log in at `https://blue-snake-221127.hostingersite.com/pathwaytt/admin`, open
**Settings → Matching & FX**, and set the real TTD/USD rate.

## 5. Cron — the whole background system

**Advanced → Cron Jobs.** Choose **Custom**, set every field to `*` (every minute), and
enter the command:

```
/opt/alt/php83/usr/bin/php /home/u269010508/domains/blue-snake-221127.hostingersite.com/public_html/pathwaytt/artisan schedule:run >> /dev/null 2>&1
```

Cron does not read your alias, so the full 8.3 path is required here. This one entry drains the job queue
each minute (resume parsing, match recomputation, gap plans) and runs the hourly job-board
sync. Nothing else needs to run in the background.

## 6. Verify

- `https://blue-snake-221127.hostingersite.com/pathwaytt/up` returns 200.
- `https://blue-snake-221127.hostingersite.com/pathwaytt/.env` and `.../pathwaytt/storage/`
  return **403** (the root `.htaccess` is active). If either returns the file, the
  `.htaccess` was not extracted — re-upload `scripts/hostinger.htaccess` as
  `pathwaytt/.htaccess`.
- The home page loads with styling (assets resolve under `/pathwaytt/build/`).
- Register a test account; the verification email arrives (or appears in
  `storage/logs/laravel.log` when `MAIL_MAILER=log`).
- Upload a resume; within a minute the profile review screen fills in (cron works).
- **Admin → Jobs → Job sync → Run sync now** imports listings and the run rows show
  notes, not errors. `cURL error 60` means PHP has no CA bundle: set `curl.cainfo` in
  PHP options or ask Hostinger support.

## 7. Updating to a new version

On your PC: `powershell -ExecutionPolicy Bypass -File scripts\build-hostinger.ps1`.

On Hostinger:

1. Upload the new zip into `public_html/pathwaytt` and extract it **here**, overwriting
   when asked. `.env` and `storage/app/private` are not in the zip, so they survive.
2. Over SSH:

```bash
alias php=/opt/alt/php83/usr/bin/php
cd ~/domains/blue-snake-221127.hostingersite.com/public_html/pathwaytt
php artisan down
php artisan migrate --force
php artisan db:seed --force
php artisan optimize:clear && php artisan optimize
php artisan up
```

## 8. Backups

Hostinger keeps weekly (Premium) or daily (Business) backups under **Files → Backups**.
Those cover the database and `public_html`, including uploaded resumes in
`pathwaytt/storage/app/private`. Resumes are personal data: keep backups on the same
retention terms as the app.
