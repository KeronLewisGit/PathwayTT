# Local Trinidad & Tobago job boards — crawlers

None of the local boards offer an API, so PathwayTT crawls their public HTML **once a
day**. Every crawler checks the site's `robots.txt` before each request, identifies itself
as `PathwayTT`, waits 1.5 s between page fetches, and always links users to the original
posting. What we read on each site on **2026-09-03** and the resulting default:

| Board | robots.txt | Terms of use | Default |
|---|---|---|---|
| **CaribbeanJobs.com** | `User-agent: * Allow: /` with `Content-Signal: search=yes, ai-train=no, use=reference`. Named AI crawlers are blocked; four internal endpoints are disallowed. | No clause on automated access or aggregation. | **On.** We behave as a search index: title, employer, location, date and the listing's own short excerpt, linking out. No full descriptions, no model training. |
| **TrinidadJob.com** (read 2026-09-07) | Allows all. | No terms-of-use page exists; the privacy policy has no clause on automated access, copying or aggregation. Listings are served by the site's standard public WordPress REST API (`/wp-json/wp/v2/job-listings`), not scraped from HTML. | **On.** Polled at most twice a day, one page per request; full listing text with the board named and linked on every row. Re-verify if a terms page appears. |
| **Pin.tt** (classifieds, read 2026-09-07) | Allows all. | Rules: "You may not copy, reproduce, distribute, publish … or in any way exploit for commercial gains or otherwise, any content or posted information on the Site." | **Not built.** Copying ads elsewhere is expressly prohibited. |
| **TnTYellow.com/jobs** (read 2026-09-07) | Allows all. | "must not access … through any automated means (including … scripts or webcrawlers)"; only search engines are exempt. | **Not built.** |
| **JobsTrinidad.com** (read 2026-09-07) | `Disallow: /job/`, `/jobs/` and every listing path. | — | **Not built.** robots.txt forbids reading listings. |
| **RealJobsTT.com** (read 2026-09-07) | Behind authentication (HTTP 401). | — | **Not built.** |
| **JobsTT.com** | Allows all. | Prohibits "data mining, robots or similar data gathering or extraction methods" and to "aggregate, copy or duplicate … any of the JobsTT Content". | **Off.** Running it without JobsTT's consent would breach their terms. Ask JobsTT for a feed or written permission, then set `JOBSOURCE_JOBSTT=true`. |
| **EmployTT.gov.tt** (Government of T&T, operated by iGovTT) | Allows all. | "You may not … reproduce or publicly display … or otherwise use them … for any public or commercial purpose without the written permission of iGovTT"; use "on any other website … for any purpose is prohibited". | **Off.** Request written permission from iGovTT / the Ministry of Labour — a free public-benefit job-matching service is a strong case — then set `JOBSOURCE_EMPLOYTT=true`. |

Re-verify the terms before enabling anything; keep a copy of any permission received with
the deployment notes.

## Operation

- `php artisan job:sync` runs all enabled sources. The scheduler calls it hourly; each
  local board enforces a 24-hour window (`min_interval_minutes = 1440`), so they are
  crawled at most once a day regardless.
- Page caps: CaribbeanJobs 4 index pages (100 listings) by default
  (`JOBSOURCE_CARIBBEANJOBS_PAGES`), JobsTT 3 pages (`JOBSOURCE_JOBSTT_PAGES`), EmployTT
  the single list page.
- The **Admin → Jobs → Job sync** page shows each run: fetched/created/updated counts, a
  note when a board was skipped inside its window or pages were disallowed by robots.txt,
  and any error.
- HTML changes on a board break its parser silently (zero results). The sync dashboard
  makes that visible; the parsers live in `app/Services/JobSources/*Source.php` with
  fixtures from the live pages in `tests/Fixtures/localboards/`.

## Adding another board

Extend `App\Services\JobSources\HtmlBoardSource`, implement `key()`, `pages()` (yield
arrays of raw card data per page, using `getHtml()` which enforces robots.txt and the
delay) and `map()` (raw → `JobDto`). Register it in `config/jobsources.php` under
`sources`, `labels` and `remote.boards`, and record the site's robots.txt and terms in the
class docblock with the date you read them.
