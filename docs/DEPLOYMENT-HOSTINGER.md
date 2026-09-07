# Deploying PathwayTT to Hostinger shared hosting

Step-by-step for Hostinger's hPanel. The project lives **directly inside** `public_html`
(Hostinger's recommended layout) and a root `.htaccess` routes every request into
Laravel's `public/` folder. The generic version (cPanel, VPS) is in
[DEPLOYMENT.md](DEPLOYMENT.md).

This guide is written for the current install:

| Item        | Value                                                                                   |
|-------------|-----------------------------------------------------------------------------------------|
| Site        | `https://blue-snake-221127.hostingersite.com/`                                          |
| App folder  | `/home/u269010508/domains/blue-snake-221127.hostingersite.com/public_html`              |
| SSH user    | `u269010508`                                                                            |
| PHP (CLI)   | `/opt/alt/php83/usr/bin/php` — the plain `php` command is 8.2 and will not run the app  |

When you later attach a real domain, replace the site name throughout. The bundle also
works from a subfolder (e.g. `public_html/pathwaytt` served at `/pathwaytt`) with no
changes other than `APP_URL` and the cron path.

**Plan requirement:** Premium or Business shared hosting. Both include SSH, Composer and
cron. Hostinger has no Node, so front-end assets are compiled on your PC and shipped in the
zip.

## How the layout stays safe

- The zip ships a root `.htaccess` (source: `scripts/hostinger.htaccess`) that rewrites
  every request into `public/`, returns **403** for direct requests to `.env`, `artisan`,
  `app/`, `config/`, `storage/`, `vendor/` and the other internals, and leaves
  `/.well-known/` alone for SSL validation.
- `public/index.php` detects this layout and tells Laravel the correct base URL, so
  routes, redirects, Livewire, Filament and asset links all resolve.
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

1. **Websites.** The site must be a plain hosting website. If it was created with
   Hostinger Website Builder or as a WordPress install, the builder overrides
   `public_html` and nothing you upload will show; remove the builder first.
2. **Websites → Manage → Advanced → PHP Configuration.**
   - PHP version: **8.3**.
   - *PHP options* tab: `memory_limit` **512M**, `upload_max_filesize` **10M**,
     `post_max_size` **12M**, `max_execution_time` **120**.
   - *PHP extensions* tab: make sure `intl`, `gd`, `exif`, `zip`, `fileinfo`, `dom`,
     `mbstring`, `pdo_mysql`, `curl`, `openssl` are ticked (most are on by default).
3. **Databases → Management → Create new MySQL database.** Hostinger names it and its user
   `u269010508_<name>`, e.g. `u269010508_pathwaytt`. The host is `localhost`.
4. **Emails.** Create a mailbox for verification and password-reset mail. On the temporary
   `hostingersite.com` domain you may not be able to; keep `MAIL_MAILER=log` for now and
   read verification links from `storage/logs/laravel.log`.
5. **Advanced → SSH Access.** Enable, and note host and port. Connect with PowerShell:
   `ssh -p PORT u269010508@HOST`.
6. **PHP on the command line.** The `php` command over SSH is 8.2. At the start of every
   SSH session run:

   ```bash
   alias php=/opt/alt/php83/usr/bin/php
   php -v        # must print PHP 8.3.x
   ```

## 3. Upload and extract into public_html

1. **Files → File Manager**, open `domains/blue-snake-221127.hostingersite.com/public_html/`.
2. Delete anything already there (Hostinger's placeholder `default.php` and similar).
3. Upload `pathwaytt-<version>.zip` into `public_html`.
4. Right-click the zip → **Extract** → extract **here** (into `public_html` itself, not a
   subfolder). Turn on *Show hidden files*. You must see `public_html/.htaccess`,
   `public_html/artisan` and `public_html/public/`.
5. Delete the zip.

If the extractor created a nested folder instead (`public_html/pathwaytt-1.4.4/artisan` or
similar), flatten it over SSH:

```bash
cd ~/domains/blue-snake-221127.hostingersite.com/public_html
shopt -s dotglob; mv pathwaytt-1.4.4/* . ; shopt -u dotglob; rmdir pathwaytt-1.4.4
ls -la          # .htaccess artisan public/ vendor/ ... all at this level
```

## 4. Configure the app

```bash
alias php=/opt/alt/php83/usr/bin/php
cd ~/domains/blue-snake-221127.hostingersite.com/public_html
cp .env.example .env
nano .env
```

Change these lines (leave the rest as in the example):

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://blue-snake-221127.hostingersite.com

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

Optional: `RESUME_PARSER_DRIVER=llm` plus `ANTHROPIC_API_KEY=` for LLM resume parsing;
`JOBSOURCE_*` toggles for which boards to crawl. Save with `Ctrl+O`, `Enter`, `Ctrl+X`.

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

Log in at `https://blue-snake-221127.hostingersite.com/admin`, open
**Settings → Matching & FX**, and set the real TTD/USD rate.

## 5. Cron — the whole background system

**Advanced → Cron Jobs.** Choose **Custom**, set every field to `*` (every minute), and
enter the command:

```
/opt/alt/php83/usr/bin/php /home/u269010508/domains/blue-snake-221127.hostingersite.com/public_html/artisan schedule:run >> /dev/null 2>&1
```

Cron does not read your alias, so the full 8.3 path is required here. This one entry
drains the job queue each minute (resume parsing, match recomputation, gap plans) and runs
the hourly job-board sync. Nothing else needs to run in the background.

## 6. Verify

- `https://blue-snake-221127.hostingersite.com/up` returns 200.
- `https://blue-snake-221127.hostingersite.com/.env` and `/storage/` return **403** (the
  root `.htaccess` is active).
- The home page loads with styling.
- Register a test account; the verification email arrives (or appears in
  `storage/logs/laravel.log` when `MAIL_MAILER=log`).
- Upload a resume; within a minute the profile review screen fills in (cron works).
- **Admin → Jobs → Job sync → Run sync now** imports listings and the run rows show
  notes, not errors. `cURL error 60` means PHP has no CA bundle: set `curl.cainfo` in
  PHP options or ask Hostinger support.

## 7. Troubleshooting

Run over SSH from `public_html`:

```bash
ls -la | head                                                   # .htaccess artisan public/ present?
curl -sI https://blue-snake-221127.hostingersite.com/public/up | head -1
curl -sI https://blue-snake-221127.hostingersite.com/up | head -1
```

| Symptom | Cause | Fix |
|---|---|---|
| Hostinger "page does not exist" on every URL, even `/public/up` | Files not in `public_html`, or the site is a Builder/WordPress site | Flatten nested folder (step 3); check site type in hPanel |
| `/public/up` is 200 but `/up` is 404 | Root `.htaccess` missing or ignored | `ls -la .htaccess`; if missing, re-upload `scripts/hostinger.htaccess` as `.htaccess`. If present, delete its `Options` line: `sed -i '/^Options/d' .htaccess` |
| 500 error | PHP failing | `tail -30 storage/logs/laravel.log`; usually `.env` missing, wrong DB password, or `storage` not writable |
| "requires PHP >= 8.3" in browser | Site PHP still 8.2 | hPanel → PHP Configuration → 8.3 |
| "requires PHP >= 8.3" over SSH | Shell `php` is 8.2 | `alias php=/opt/alt/php83/usr/bin/php` |
| Unstyled page | Assets not found | `ls public/build/manifest.json`; rebuild the zip if missing |
| Links point to the wrong host | `APP_URL` wrong | Fix `.env`, then `php artisan optimize:clear && php artisan optimize` |
| Registration returns 500; log says SMTP `554 Client host rejected` or `535 authentication failed` | `MAIL_USERNAME`/`MAIL_PASSWORD` are `null` or wrong, or the from-address is not a mailbox on this account | Put real mailbox credentials in `.env`, or `MAIL_MAILER=log` until a mailbox exists; then `php artisan optimize:clear && php artisan optimize` |
| `git pull` says "not a git repository" | `public_html` came from the zip, not a clone | One-time setup in section 8A |

## 8. Updating to a new version

Two workflows. Git is simpler once set up; the zip needs no GitHub access from the server.

### A. Git pull (recommended)

One-time setup on the server, turning `public_html` into a checkout of the repo. Nothing
untracked (`.env`, `vendor/`, uploaded resumes) is touched:

```bash
cd ~/domains/blue-snake-221127.hostingersite.com/public_html
git init -b main
git remote add origin https://github.com/KeronLewisGit/PathwayTT.git
git fetch origin main
git reset --hard origin/main
```

If the repository is private, GitHub prompts for a username and a personal access token
(GitHub → Settings → Developer settings → Fine-grained tokens, read access to this repo).

Each release, on your PC: `npm run build`, commit, `git push`. The compiled assets in
`public/build` are tracked precisely so the server needs no Node. On the server:

```bash
alias php=/opt/alt/php83/usr/bin/php
cd ~/domains/blue-snake-221127.hostingersite.com/public_html
php artisan down
git pull
php $(which composer) install --no-dev --optimize-autoloader --no-interaction   # only when composer.lock changed
php artisan migrate --force
php artisan db:seed --force
php artisan optimize:clear && php artisan optimize
php artisan up
```

### B. Zip upload

On your PC: `powershell -ExecutionPolicy Bypass -File scripts\build-hostinger.ps1`.

On Hostinger:

1. Upload the new zip into `public_html` and extract it **here**, overwriting when asked.
   `.env` and `storage/app/private` are not in the zip, so they survive.
2. Over SSH:

```bash
alias php=/opt/alt/php83/usr/bin/php
cd ~/domains/blue-snake-221127.hostingersite.com/public_html
php artisan down
php artisan migrate --force
php artisan db:seed --force
php artisan optimize:clear && php artisan optimize
php artisan up
```

## 9. Backups

Hostinger keeps weekly (Premium) or daily (Business) backups under **Files → Backups**.
Those cover the database and `public_html`, including uploaded resumes in
`storage/app/private`. Resumes are personal data: keep backups on the same retention
terms as the app.
