# Deploying PathwayTT to Hostinger shared hosting

Step-by-step for Hostinger's hPanel. The generic version (cPanel, VPS) is in
[DEPLOYMENT.md](DEPLOYMENT.md); read that for background on *why* each step exists.

**Plan requirement:** Premium or Business shared hosting. Both include SSH, Composer and
cron. Hostinger has no Node, so front-end assets are compiled on your PC and shipped in the
zip. (Single/Starter plans lack SSH; the app can still be installed with File Manager only,
but running `artisan` commands requires SSH, so avoid those plans.)

Throughout, replace:

| Placeholder    | Meaning                                                                 |
|----------------|-------------------------------------------------------------------------|
| `YOURDOMAIN`   | the domain or subdomain, e.g. `pathwaytt.com` or `app.pathwaytt.com`    |
| `USERNAME`     | your Hostinger account user, looks like `u123456789`                    |
| `~`            | `/home/USERNAME` — run `pwd` after logging in over SSH to confirm       |

## 1. Build the upload bundle on your PC

```powershell
powershell -ExecutionPolicy Bypass -File scripts\build-hostinger.ps1
```

This compiles the Vite assets, installs production-only Composer packages into a staging
copy (your local `vendor/` is untouched), strips `.env`, logs, sessions, uploaded resumes
and tests, and writes `dist\pathwaytt-<version>.zip` (about 24 MB). The zip is written
with forward-slash paths so Linux extracts it correctly.

## 2. Prepare the hosting account (hPanel)

1. **Websites → Manage → Advanced → PHP Configuration.**
   - PHP version: **8.3**.
   - *PHP options* tab: `memory_limit` **512M**, `upload_max_filesize` **10M**,
     `post_max_size` **12M**, `max_execution_time` **120**.
   - *PHP extensions* tab: make sure `intl`, `gd`, `exif`, `zip`, `fileinfo`, `dom`,
     `mbstring`, `pdo_mysql`, `curl`, `openssl` are ticked (most are on by default).
2. **Databases → Management → Create new MySQL database.** Note the database name, user and
   password. Hostinger prefixes both name and user with `USERNAME_`, e.g.
   `u123456789_pathwaytt`. The host is `localhost`.
3. **Emails.** Create a mailbox such as `hello@YOURDOMAIN` (used for verification and
   password-reset mail). Note its password.
4. **Advanced → SSH Access.** Enable, and note host, port and username. Connect with
   PowerShell: `ssh -p PORT USERNAME@HOST`.

## 3. Upload and extract

1. **Files → File Manager**, open `domains/YOURDOMAIN/`.
2. Upload `pathwaytt-<version>.zip` into that folder (next to `public_html`, not inside it).
3. Right-click the zip → **Extract** → into a new folder named **`pathwaytt`**.
   You should end up with `domains/YOURDOMAIN/pathwaytt/artisan` present.
4. Delete the zip.

## 4. Point the web root at `public/`

Hostinger fixes the document root at `domains/YOURDOMAIN/public_html`. Laravel must serve
from `pathwaytt/public`, and `.env` / `storage` must stay outside the web root. Over SSH:

```bash
cd ~/domains/YOURDOMAIN
rm -rf public_html
ln -s ~/domains/YOURDOMAIN/pathwaytt/public public_html
ls -la            # public_html -> /home/USERNAME/domains/YOURDOMAIN/pathwaytt/public
```

If `ln` is refused on your plan, fall back to copying instead: copy the *contents* of
`pathwaytt/public/` into `public_html/`, then edit `public_html/index.php` so the two
`require` lines read `__DIR__.'/../pathwaytt/vendor/autoload.php'` and
`__DIR__.'/../pathwaytt/bootstrap/app.php'`. Re-copy `public/build/` on every update.

## 5. Configure the app

```bash
cd ~/domains/YOURDOMAIN/pathwaytt
cp .env.example .env
nano .env
```

Change these lines (leave the rest as in the example):

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://YOURDOMAIN

DB_DATABASE=USERNAME_pathwaytt
DB_USERNAME=USERNAME_pathwaytt
DB_PASSWORD=the-database-password

MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=hello@YOURDOMAIN
MAIL_PASSWORD=the-mailbox-password
MAIL_FROM_ADDRESS="hello@YOURDOMAIN"

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
php artisan mail:test you@example.com   # confirm SMTP works
```

If `php` is not 8.3 (`php -v`), use the full path instead, e.g. `/usr/bin/php8.3` or
`/opt/alt/php83/usr/bin/php` (`ls /opt/alt/ | grep php` shows what is installed).

Create the first admin:

```bash
php artisan tinker
>>> $u = App\Models\User::create(['name' => 'Admin', 'email' => 'you@YOURDOMAIN', 'password' => 'choose-a-strong-password']);
>>> $u->forceFill(['is_admin' => true, 'email_verified_at' => now()])->save();
>>> exit
```

Log in, open **Admin → Settings → Matching & FX**, and set the real TTD/USD rate.

## 6. Cron — the whole background system

**Advanced → Cron Jobs.** Choose **Custom**, set every field to `*` (every minute), and
enter the command:

```
/usr/bin/php /home/USERNAME/domains/YOURDOMAIN/pathwaytt/artisan schedule:run >> /dev/null 2>&1
```

Use the same PHP binary path that worked in step 5. This one entry drains the job queue
each minute (resume parsing, match recomputation, gap plans) and runs the hourly job-board
sync. Nothing else needs to run in the background.

## 7. Verify

- `https://YOURDOMAIN/up` returns 200.
- Register a test account; the verification email arrives.
- Upload a resume; within a minute the profile review screen fills in (cron works).
- **Admin → Jobs → Job sync → Run sync now** imports listings and the run rows show
  notes, not errors. `cURL error 60` means PHP has no CA bundle: set `curl.cainfo` in
  PHP options or ask Hostinger support.
- Hostinger enables a free SSL certificate automatically; if the site shows as insecure,
  **Security → SSL → Install**.

## 8. Updating to a new version

On your PC: `powershell -ExecutionPolicy Bypass -File scripts\build-hostinger.ps1`.

On Hostinger:

1. Upload the new zip to `domains/YOURDOMAIN/` and extract it **over** the existing
   `pathwaytt` folder (File Manager asks to overwrite — say yes). `.env` and
   `storage/app/private` are not in the zip, so they survive.
2. Over SSH:

```bash
cd ~/domains/YOURDOMAIN/pathwaytt
php artisan down
php artisan migrate --force
php artisan db:seed --force
php artisan optimize:clear && php artisan optimize
php artisan up
```

If you used the copy fallback in step 4, also re-copy `public/build/` into `public_html/`.

## 9. Backups

Hostinger keeps weekly (Premium) or daily (Business) backups under **Files → Backups**.
Those cover the database and the `pathwaytt` folder, including uploaded resumes in
`storage/app/private`. Resumes are personal data: keep backups on the same retention terms
as the app.
