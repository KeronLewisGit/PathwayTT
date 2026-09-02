# PathwayTT

Employment assistance for job seekers in Trinidad & Tobago. Upload a resume, say what
you're looking for, and get a ranked list of jobs you are actually eligible for — local
and internationally remote — with a plain-English explanation of every score. When nothing
fits, the app pivots to a **Skills Gap Plan**: the skills that would open the most listings
for the least effort, split into what you can study locally in T&T and what's available
online, with projections computed from real listings.

Built with Laravel 12, Livewire 3, Filament 3 and Tailwind. Runs on a plain shared-hosting
LAMP account; Docker is provided for local preview only.

## Features

- **Resume pipeline** — private PDF/DOCX upload, queued parsing (rule-based by default,
  optional LLM structurer), and an editable review screen; parser output never overwrites
  what the user has corrected.
- **Job sources** — manual entry, CSV import, and public remote job boards (Remotive,
  Jobicy, Himalayas; Remote OK and Arbeitnow optional). Each board's terms are recorded in
  its adapter and honoured in the UI (named source, follow link to the original posting).
- **T&T eligibility** — listings restricted to other countries, or to timezone windows
  that exclude UTC-4, are hard-filtered rather than scored low. Remote realities (contractor
  vs employee, USD pay, overlap hours) are modelled; local realities (NIS, BIR, CSEC/CAPE)
  are first-class.
- **Matching** — 0–100 score across seven weighted factors with a stored line-by-line
  breakdown; missing required skills cap the score; weights, threshold and FX rate are
  admin-editable at runtime.
- **Advisory mode** — gap analysis ranked by impact per week of effort, resources in local
  and online tracks, non-credential advice (portfolio, USD payments, overlap statement),
  persisted plan versions with progress, PDF export.
- **Tracker** — saved → applied → interviewing → offer / rejected, with notes.
- **Admin (Filament)** — jobs, industries, skills + aliases, learning resources, users,
  job-sync dashboard with CSV upload, matching weights & FX settings.

## Local setup

Requirements: PHP 8.3 with `pdo_mysql intl mbstring zip gd`, Composer, MySQL 8, Node 20.

```bash
git clone <repo> PathwayTT && cd PathwayTT
composer install
cp .env.example .env          # then set DB_* and MAIL_*
php artisan key:generate
php artisan migrate --seed    # reference data; demo users/jobs too when APP_ENV=local
npm install && npm run build
php artisan serve             # http://localhost:8000
php artisan queue:work        # second terminal: parsing, matching, plans
```

Demo accounts (local only): `demo@pathwaytt.test` and `admin@pathwaytt.test`, password
`password`. The admin panel is at `/admin`.

Windows/Laragon users: PHP needs a CA bundle for the job-board adapters —
`curl.cainfo` / `openssl.cafile` in `php.ini` pointing at Laragon's `etc/ssl/cacert.pem`.

### Docker (preview)

`docker compose up -d --build` → app on http://localhost:8088, Mailpit on :8025. See
[docs/DOCKER.md](docs/DOCKER.md).

## Configuration

| Variable | Purpose |
|---|---|
| `APP_DISPLAY_TIMEZONE` | Display timezone (storage is UTC). Default `America/Port_of_Spain`. |
| `QUEUE_VIA_SCHEDULER` | `true` on shared hosting: `schedule:run` drains the queue each minute. `false` with a real worker. |
| `RESUME_PARSER_DRIVER` | `rule` (default, no key) or `llm`. |
| `ANTHROPIC_API_KEY`, `ANTHROPIC_MODEL` | Used only when the driver is `llm`. Falls back to `rule` on any error. |
| `JOBSOURCE_*` | Enable/disable each remote board; per-run cap; whether to import listings closed to T&T. |

Matching weights, the advisory threshold and the TTD/USD rate live in the database and are
edited under **Admin → Settings → Matching & FX** (`config/matching.php` holds defaults).
The seeded FX rate is a placeholder flagged for review.

## Job sources

`php artisan job:sync` runs every enabled source (nightly at 03:00 AST via the scheduler;
admins can also run it from **Admin → Jobs → Job sync**). Local T&T boards have no public
APIs — enter local jobs manually or import the CSV template at
`docs/job-import-template.csv`. Remote boards are throttled per their published limits and
listings not open to T&T residents are skipped at import by default.

## Tests

```bash
php artisan test
```

Tests always run against in-memory SQLite and never reach the network; the base TestCase
enforces both.

## Deployment

See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) for shared-hosting (cPanel) and VPS steps.

## Project layout

- `docs/SPEC.md` — the product specification this build follows.
- `app/Services/Resume` — text extraction and structuring.
- `app/Services/JobSources` — source adapters and the ingestor.
- `app/Services/Matching` — scorer, candidate snapshot, recompute.
- `app/Services/Advisory` — skills-gap analyzer and planner.
- `config/matching.php`, `config/advisory.php`, `config/jobsources.php`, `config/resume.php` — all tunables.
