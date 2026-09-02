# Running PathwayTT with Docker

A self-contained preview stack: Apache + PHP 8.3 (the same shape as the
shared-host production target), MySQL 8.4, a queue worker, a cron stand-in
for the scheduler, and Mailpit to catch outgoing email.

Docker is **for local preview and development only**. Production remains a
plain LAMP deployment (see docs/SPEC.md); nothing in the app depends on Docker.

## Requirements

- Docker Desktop 4.x (Compose v2). Verified with Docker 28 / Compose 2.40.
- Internet access to Docker Hub, Packagist and npm **during the first build**.
  On the office network those hosts are blocked, so build from another network
  the first time; subsequent builds reuse cached layers.

## First run

```powershell
cd C:\laragon\www\PathwayTT
docker compose up --build
```

The first build takes a few minutes (PHP extensions, Composer, Vite). When
the `app` container logs `starting: apache2-foreground`, open:

| What            | URL                     |
|-----------------|-------------------------|
| App             | http://localhost:8088   |
| Admin panel     | http://localhost:8088/admin |
| Mailpit (email) | http://localhost:8025   |

On first start the web container runs migrations and seeds reference data
(industries, skills, learning resources, settings). Because `APP_ENV=local`
the demo accounts and demo job listings are seeded too:

| Account              | Password   |
|----------------------|------------|
| demo@pathwaytt.test  | `password` |
| admin@pathwaytt.test | `password` |

Port 8088 is used so the stack can run alongside `php artisan serve` on 8000
(8080 is usually held by the WSL relay on Windows). Override it with an
environment variable: `$env:APP_PORT = 8090; docker compose up -d`.

## Day to day

```powershell
docker compose up -d            # start in the background
docker compose logs -f app      # web logs (Laravel logs go to stderr)
docker compose logs -f queue    # parsing / matching / gap-plan jobs
docker compose down             # stop; database and uploads are kept
docker compose down -v          # stop and wipe everything (fresh seed next time)
```

After changing PHP, Blade, CSS or JS, rebuild the image:

```powershell
docker compose up -d --build
```

## Useful commands inside the container

```powershell
docker compose exec app php artisan test                 # full Pest suite
docker compose exec app php artisan job:sync             # run the job importers now
docker compose exec app php artisan db:seed --force      # re-run seeders (idempotent)
docker compose exec app php artisan tinker
docker compose exec db mysql -upathwaytt -ppathwaytt pathwaytt
```

To import jobs from CSV, upload through Admin → Jobs → Job sync, or drop a
file into the storage volume:

```powershell
docker compose cp docs/job-import-template.csv app:/var/www/html/storage/app/private/import/jobs/
docker compose exec app php artisan job:sync --source=csv
```

## Configuration

All settings are environment variables in `docker-compose.yml` (the
`x-app-env` block); the container has no `.env` file. Two values are read
from the Laravel `.env` next to the compose file if it exists:

- `APP_KEY` — reused so sessions match your Laragon setup. If absent, the
  web container generates a key once and persists it in the storage volume.
- `ANTHROPIC_API_KEY` / `RESUME_PARSER_DRIVER` — for the LLM resume
  structurer (Phase 7); leave unset to use the rule-based parser.

Uploaded resumes and CSV imports live in the `app_storage` volume, outside
the image and outside the web root.

## Troubleshooting

- **Build fails fetching packages** — you are on the allow-listed office
  network. Switch networks and re-run `docker compose build`.
- **Port already allocated** — something else is on 8088 or 8025. Set
  `APP_PORT` to a free port, or change the Mailpit mapping in `docker-compose.yml`.
- **Pages stuck on "Computing…"** — the queue container isn't running:
  `docker compose ps`, then `docker compose up -d queue`.
- **Emails never arrive** — they never leave the machine; look in Mailpit.
