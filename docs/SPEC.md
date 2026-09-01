# Claude Code Prompt — Employment Assistance Web App (Trinidad & Tobago)

> Paste everything below the line into Claude Code as your opening prompt.
> Keep this file in the repo as `docs/SPEC.md` so Claude can re-read it in later sessions.

---

## Role and objective

You are building a Laravel web application called **PathwayTT** (rename if I say otherwise): an employment assistance platform for job seekers in **Trinidad and Tobago**.

A user uploads their resume, tells us the industry they want to work in and their preferred work arrangement, and the app returns a ranked list of jobs they are genuinely eligible for. When there are no good matches — or the user's match scores are all low — the app must pivot to **advisory mode** and tell the user exactly which certifications, courses, or skills would raise their score, distinguishing what is obtainable **locally in T&T** from what is obtainable **online/internationally**.

**Do not start coding yet.** First read this whole spec, then produce a build plan (phases, file tree, migration list, open questions). Ask me the open questions, wait for my answers, then build Phase 1 only. Stop after each phase and show me what to test.

---

## Context that shapes the product

- Users are in Trinidad & Tobago (timezone **AST, UTC-4**, no DST — this happens to align with US Eastern for half the year, which is a selling point for remote work; surface it).
- **"Remote" means internationally remote** — a job hosted by a foreign company that a T&T resident can perform from home. This is a distinct category from "local on-premises" and needs its own eligibility rules.
- Salary can be **TTD or USD**. Store currency explicitly, never assume. Show both with a configurable FX rate (admin-editable, not hardcoded in views).
- Local hiring realities to model: NIS number, BIR file number, police certificate of character for some roles, driver's permit, CXC/CSEC and CAPE passes as baseline qualifications alongside degrees.
- Remote/international realities to model: contractor vs employee classification, whether the employer hires "worldwide" vs "US only" vs "LATAM/Caribbean", payment method availability from T&T (Wise, Payoneer, direct USD wire, crypto), and overlap requirements with the employer's working hours.

---

## Stack and conventions

- **Laravel 12** (latest stable), **PHP 8.3+**, **MySQL 8**.
- **Blade + Livewire 3** + **Tailwind CSS** for the UI. No SPA.
- **Laravel Breeze** for auth (email/password), email verification on.
- **Filament v3** for the admin panel — do not hand-roll CRUD.
- **Queues** (`database` driver by default) for resume parsing and job ingestion; nothing slow in the request cycle.
- **Pest** for tests.
- Must run **locally** (`php artisan serve` / Herd / Sail — pick one and document it) **and on a normal shared/VPS LAMP host**. No dependency that requires Docker in production. If a queue worker isn't available in production, provide a `schedule:run` fallback path.
- Follow Laravel conventions strictly: form requests for validation, policies for authorization, service classes for business logic, no fat controllers, no business logic in Blade.
- Every non-trivial service gets an interface + a bound implementation so drivers can be swapped.

---

## Core user flow

1. Register / log in.
2. **Upload resume** (PDF or DOCX, max 5 MB) → parsed into structured profile data.
3. **Review & correct** the parsed profile (this is essential — parsing is never perfect; the user must be able to edit every extracted field, add skills, and confirm).
4. **Set job preferences**: target industry, work arrangement (`remote_international` | `contract` | `on_premises` | any combination), desired seniority, minimum salary + currency, willingness to relocate within T&T (Trinidad vs Tobago), availability date.
5. **See ranked matches** with a visible score, a plain-English explanation of the score, and a per-job "what you're missing" list.
6. If zero matches, or all matches score below the configurable threshold (default 55), show the **Skills Gap Plan** instead of an empty state — never show an empty results page with no next action.
7. Save/track jobs (`saved` → `applied` → `interviewing` → `offer` / `rejected`) with notes.

---

## Data model (starting point — refine and justify changes)

- `users`
- `profiles` — user_id, full_name, phone, location (region in T&T), summary, years_experience, highest_education_level, has_nis, has_bir, has_drivers_permit, willing_to_relocate, availability_date
- `resumes` — user_id, original filename, stored path, extracted_text (longText), parse_status, parsed_at
- `skills` — canonical skill taxonomy: name, slug, category, aliases (JSON)
- `profile_skill` — pivot with proficiency (1–5), years_used, evidence_source (`resume` | `self_reported` | `certificate`)
- `certifications` — user's held certs: name, issuer, issued_at, expires_at, credential_url, verified (bool)
- `work_histories` — employer, title, industry, start/end, is_current, description, extracted_skills (JSON)
- `educations` — institution, qualification_type (CSEC/CAPE/Diploma/Associate/BSc/MSc/PhD/Professional), field, completed_at
- `industries` — seeded list relevant to T&T (see below)
- `jobs` — source, source_job_id, title, company, industry_id, work_arrangement, employment_type (permanent/contract/temp), location_text, country, is_open_to_caribbean (nullable bool), seniority, salary_min, salary_max, salary_currency, salary_period, description, requirements (JSON), posted_at, closes_at, apply_url, raw_payload (JSON), is_active
- `job_skill` — pivot with `is_required` (bool) and `weight`
- `job_matches` — user_id, job_id, score, score_breakdown (JSON), missing_skills (JSON), computed_at
- `applications` — user_id, job_id, status, applied_at, notes
- `learning_resources` — the recommendation catalog: title, provider, provider_type (`local_tt` | `international_online` | `hybrid`), delivery_mode, url, cost_min, cost_max, currency, duration_weeks, credential_type (certificate/diploma/degree/badge), notes
- `learning_resource_skill` — which skills a resource teaches, with `impact_weight`
- `skill_gap_plans` — user_id, generated_at, target_industry_id, payload (JSON snapshot of the plan so it's reproducible)

Seed `industries` with T&T-relevant sectors: Energy & Petrochemicals, Financial Services & Insurance, ICT & Software, Manufacturing, Distribution & Retail, Construction, Agriculture & Agro-processing, Tourism & Hospitality, Healthcare, Education, Public Sector, Creative & Media, Logistics & Shipping, BPO & Contact Centre, Professional Services (Accounting/Legal/Consulting).

---

## Resume parsing

Build a `ResumeParser` service with two layers:

1. **Text extraction** — `smalot/pdfparser` for PDF, `phpoffice/phpword` for DOCX. Store raw text.
2. **Structuring** — an interface `ResumeStructurerInterface` with two implementations:
   - `RuleBasedStructurer` — regex/heuristic section detection (Experience, Education, Skills, Certifications) + skill matching against the `skills` taxonomy including aliases. This is the default and must work with **no API key**.
   - `LlmStructurer` — calls the Anthropic API (`ANTHROPIC_API_KEY` in `.env`) with a strict JSON-only prompt and validates the response against a schema before persisting. Used when the key is present and `RESUME_PARSER_DRIVER=llm`.

Parsing runs in a queued job. The UI shows a processing state and then the editable review screen. **Never silently overwrite a field the user has manually edited** — track `is_user_edited` per field or hold user edits in a separate layer.

---

## Job sourcing (important — read carefully)

Implement an adapter pattern: `JobSourceInterface` with `fetch(): iterable<JobDto>`, plus a `job:sync` Artisan command that runs all enabled sources, normalizes, and upserts on `source + source_job_id`.

Ship these adapters:

- `ManualSource` — jobs entered by an admin in Filament. Always available.
- `CsvImportSource` — bulk import from a CSV template. Always available.
- Remote/international job board APIs that are **publicly documented and free to use**. Before wiring any of them, verify the endpoint and its terms yourself and tell me what you found — do not assume an API exists or that its shape matches what you remember. Candidates to check: Remotive, RemoteOK, Arbeitnow, Jobicy, Himalayas, USAJOBS. For each one you implement, record in the code comment: base URL, auth requirement, rate limit, and attribution requirement.
- `LocalBoardSource` — **stub only for now**. Local T&T boards (CaribbeanJobs, JobsTT, Ministry of Labour NES, employer career pages, LinkedIn) mostly have no public API. **Do not write a scraper without asking me first.** Leave a documented interface and a note about ToS/robots.txt so I can decide per source, and make manual + CSV entry the practical path for local jobs at launch.

Every job record must carry its `source` and `apply_url`, and the UI must link out to the original posting. **Never generate, invent, or seed fake job listings outside of a clearly-labelled `DemoJobSeeder` used only in local dev.**

For international remote jobs, extract or infer a `geo_eligibility` field (`worldwide` / `region_restricted` / `country_restricted`) and a `required_overlap_hours` where stated. A job restricted to US-only applicants must **not** be shown as eligible to a T&T user — flag it as ineligible with the reason.

---

## Matching and scoring

Create `MatchScoringService` producing a 0–100 score with a stored, explainable breakdown. Weights must live in `config/matching.php`, not in code:

| Component | Default weight |
|---|---|
| Required skills coverage | 35 |
| Preferred/bonus skills coverage | 10 |
| Years of experience vs required | 15 |
| Education / qualification level met | 10 |
| Industry alignment | 10 |
| Work arrangement match (user pref vs job) | 10 |
| Geo eligibility + timezone overlap feasibility | 10 |

Rules:
- **Hard filters run before scoring** and produce ineligibility, not a low score: geo restriction excludes T&T, job closed/expired, work permit required, mandatory credential the user cannot hold.
- Skill matching uses the canonical taxonomy + aliases, not raw string equality (`JS` ≈ `JavaScript`, `MS Excel` ≈ `Microsoft Excel`).
- Missing **required** skills should cap the score — e.g. missing 2+ required skills caps at 60 — so someone never sees a 90% match for a job they can't do.
- Store `score_breakdown` JSON so the UI can render "why this score" line by line, and so I can tune weights without re-deriving results.
- Recompute matches when: the profile changes, preferences change, or new jobs are ingested. Queue it.

---

## Advisory / Skills Gap mode (the differentiator — give this real attention)

Trigger when there are zero eligible jobs, or when the best score is below `config('matching.advisory_threshold')` (default 55).

`SkillGapAnalyzer` should:
1. Aggregate the missing required skills across all jobs in the user's target industry + arrangement, weighted by how frequently each skill appears and how close the user is to each job otherwise.
2. Rank the gaps by **impact per unit of effort** — the skill that unlocks the most jobs for the least time/cost goes first.
3. Map each gap to `learning_resources`, and split the output into two clearly separated tracks:
   - **Locally obtainable in T&T** — seed real institutions: UWI St. Augustine & UWI Open Campus, COSTAATT, ROYTEC, SBCS Global Learning Institute, MIC Institute of Technology (MIC-IT), NESC, Cipriani College of Labour & Co-operative Studies, YTEPP, NEDCO (entrepreneurship), plus professional bodies commonly pursued in T&T (ACCA, CIMA, CMI, PMI chapter). Seed only the institution, its general offering area, and its official URL — **do not invent specific course names, prices, or durations**; leave those nullable for an admin to fill in, and say so in the UI ("Contact provider for current course listing").
   - **Internationally / online obtainable** — Coursera, edX, Google Career Certificates, AWS/Azure/GCP certifications, CompTIA, Microsoft, Salesforce, HubSpot, Meta Blueprint, freeCodeCamp, Scrimba. Same rule: no invented prices — store cost as a nullable range with a `cost_note`.
4. Output a **plan**: 3 phases (Quick wins ≤ 4 weeks / Core credential 1–6 months / Long-term), each item showing the skill, the resource, estimated effort, and **the projected score lift and the number of currently-listed jobs it would unlock**. That projection must be computed from real data in the DB, not guessed.
5. Add non-credential advice where the data supports it: e.g. if remote-eligible jobs exist but the user has no remote-work signals, suggest a portfolio/GitHub, a Wise/Payoneer account for USD payment, and an overlap-hours statement on the CV.
6. Persist the plan so the user can revisit it and see progress as they add certifications.

---

## Admin (Filament)

CRUD for jobs, industries, skills + aliases, learning resources, users; a job-sync dashboard showing last run/count/errors per source; editable matching weights and FX rate; ability to re-run matching for a user.

---

## Non-functional requirements

- **Privacy**: resumes are PII. Store outside the public webroot, serve only through a signed, policy-gated controller route. Add "delete my resume" and "delete my account" (hard delete of files + records). Add a short privacy note at upload.
- Validate file uploads by MIME **and** extension; reject anything else.
- Rate-limit uploads and job-sync endpoints.
- All money handled as integers (cents) with an explicit currency; never float.
- Timezone: store UTC, display AST.
- Seeders: industries, skills taxonomy (~200 entries across the T&T industries above), learning resources, demo jobs (dev only), a demo user.
- Tests: scoring service unit tests (including the hard-filter and score-cap cases), gap analyzer unit tests, resume parse feature test with a fixture PDF, an end-to-end feature test of upload → preferences → matches.
- `README.md` with local setup, `.env.example`, seeding, and shared-host deployment steps.

---

## Build phases

1. **Foundation** — Laravel install, auth, migrations, models, seeders, Filament, base layout.
2. **Resume pipeline** — upload, queued parse, rule-based structuring, editable review screen.
3. **Jobs + preferences** — manual/CSV sources, `job:sync`, preferences form, job listing UI.
4. **Matching** — scoring service, breakdown UI, saved jobs and application tracker.
5. **Advisory mode** — gap analyzer, learning resource catalog, phased plan UI.
6. **Remote-job adapters** — verified external APIs, geo-eligibility handling.
7. **Polish** — dashboard, PDF export of the gap plan, tests to green, deployment docs.

---

## Guardrails

- If you are unsure whether an external API, package, or institution's details are current, **say so and verify** rather than writing code against a remembered shape.
- Do not fabricate job listings, course prices, salary figures, or provider details. Nullable + admin-editable beats invented.
- Do not build a scraper for any site without asking me first.
- Prefer boring, maintainable Laravel over clever abstractions.
- At the end of each phase: list what you built, what you assumed, and how I test it.

## Open questions to ask me before Phase 1

Include your own, but at minimum: app name; single-tenant or multi-tenant; whether users should be able to apply in-app or only link out; whether I want an employer-side portal later (it changes the schema now); expected user volume; and target hosting.
