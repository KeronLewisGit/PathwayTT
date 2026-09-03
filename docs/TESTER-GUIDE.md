# PathwayTT — Tester guide

Thanks for trying PathwayTT. It helps job seekers in Trinidad & Tobago find jobs they're
genuinely eligible for — local and internationally remote — and, when nothing fits yet,
shows exactly which skills would open the most doors and where to get them.

Everything you do stays inside this test instance. Listings marked **[DEMO]** are
fictional; the rest come from public remote job boards.

## Accounts (password for all: `password`)

| Account | Who | Use it to… |
|---|---|---|
| `demo@pathwaytt.test` | **Aaliyah Mohammed** — customer-service professional, 3 years, mid-search | See the app *working*: scored matches, a skills plan, tracked applications, milestones. |
| `marcus@pathwaytt.test` | **Marcus Charles** — welder/pipefitter, energy sector | A trades profile matching local on-site roles. |
| `tester1@pathwaytt.test` `tester2@…` `tester3@…` | Empty accounts | Go through onboarding from zero: upload a resume, review, set preferences. |

## Or register as a new user (recommended)

Use **Register** on the login screen with your name, email and a password, exactly as a
real job seeker would. You go straight to your (empty) dashboard — the experience we most
want feedback on. A banner reminds you to verify your email; do it when you can:

- If this instance sends real email, click the link in the message you receive.
- If it's a test instance that captures email, the banner tells you where to read it
  (a shared inbox page) — open it, find the message with your address, click the link.

## What to try, in order

1. **Dashboard** — read the "Next step", profile strength, this week's goal and milestones.
   Does it tell you what to do next without explaining anything?
2. **My Resume** — upload any PDF or DOCX resume (a real one or a rough draft). Within a
   minute, **My Profile** fills in. Correct anything wrong; add skills by searching.
3. **Preferences** — pick an industry and how you want to work (local, hybrid, remote).
4. **Matches** — every open listing you're eligible for, ranked. Open "Why this score?"
   and "What you're missing". Tick "Show ineligible" to see jobs closed to T&T residents
   and the reason.
5. **Skills Plan** — the skills that would open the most listings for the least effort,
   with local T&T providers and online options. Add one of those skills on My Profile and
   watch the plan and your best score move. Download the PDF.
6. **Jobs** — browse and filter; every listing links out to the original posting.
7. **Applications** — save a job, mark it applied, move it to interviewing. Notes are free
   text.

## What we'd like to hear

- Anything confusing, wrong or missing — especially about T&T realities (NIS/BIR, CSEC,
  local institutions, salaries in TTD/USD, remote eligibility).
- Whether the match scores and "what you're missing" feel fair and believable.
- Whether the skills plan recommendations are useful and honest (providers, effort).
- Whether the progress features (strength, milestones, weekly goal) motivate or annoy.
- Anything that looks off on your phone.

Send screenshots where you can. The page footer shows the version number.

## Resetting

An administrator can return the demo accounts to their starting state at any time:

```
php artisan demo:reset              # demo + marcus + tester accounts
php artisan demo:reset --keep-testers
```
