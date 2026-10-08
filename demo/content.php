<?php

declare(strict_types=1);

// The Halyard handbook, copied from ruvelo/laravel-wiki's demo so both demos
// show the same wiki. Each entry is one save, oldest first:
// [how long ago, author, title, Markdown, edit summary].

$deployV1 = <<<'MD'
Every merge to `main` ships to production. There is no release train and no deploy freeze.

## Shipping a change

1. Branch from `main` and open a pull request.
2. Get one approving review. For anything touching payments, two.
3. Merge. The pipeline builds, runs the test suite and deploys.

```bash
git switch -c fix/invoice-rounding
git push -u origin fix/invoice-rounding
```

## If something breaks

Revert the merge commit and push. Then write it up, see [[Incident reviews]].
MD;

$deployV2 = <<<'MD'
Every merge to `main` ships to production, usually within four minutes. There is no release train and no deploy freeze.

## Shipping a change

1. Branch from `main` and open a pull request.
2. Get one approving review. For anything touching payments, two.
3. Merge. The pipeline builds, runs the test suite and deploys.

```bash
git switch -c fix/invoice-rounding
git push -u origin fix/invoice-rounding
```

Risky changes go behind a flag first: see [[Feature flags]].

## Checking it worked

- Watch the deploy in `#ship-it`. The bot posts when the new build is live.
- Open the [[Service map]] dashboard and compare error rates for ten minutes.

## If something breaks

Revert the merge commit and push. Then write it up, see [[Incident reviews]].
MD;

$deployV3 = <<<'MD'
Every merge to `main` ships to production, usually within four minutes. There is no release train and no deploy freeze.

## Shipping a change

1. Branch from `main` and open a pull request.
2. Get one approving review. For anything touching payments, two.
3. Merge. The pipeline builds, runs the test suite and deploys.

```bash
git switch -c fix/invoice-rounding
git push -u origin fix/invoice-rounding
```

Risky changes go behind a flag first: see [[Feature flags]].

## Checking it worked

- Watch the deploy in `#ship-it`. The bot posts when the new build is live.
- Open the [[Service map]] dashboard and compare error rates for ten minutes.
- Queue depth should stay under 500. If it climbs, page whoever is on the [[On-call rota]].

## Rolling back

Revert the merge commit and push; the revert deploys like any other change. If the pipeline itself is broken:

```bash
php artisan down --secret="halyard-ops"
./deploy/rollback.sh --to=previous
php artisan up
```

Then write it up within two working days, see [[Incident reviews]].
MD;

$incidentV1 = <<<'MD'
**Status:** resolved · **Severity:** 2 · **Lead:** Kenji Mori

Invoices generated between 09:12 and 09:51 rounded VAT down instead of to the nearest cent.

## Timeline

| Time  | What happened |
|-------|---------------|
| 09:12 | Deploy of the new tax engine |
| 09:40 | Support flags three tickets about totals off by one cent |
| 09:51 | Change reverted, see [[Deploy guide]] |
| 11:30 | Affected invoices reissued |
MD;

$incidentV2 = $incidentV1.<<<'MD'


## What we're changing

- [x] Property-based tests for every rounding mode
- [x] Tax engine changes ship behind [[Feature flags]]
- [ ] Alert when support tickets mention "invoice" more than five times an hour

> Nobody did anything wrong here. The test suite only checked whole-number amounts.
MD;

return [
    ['33 days', 'Maya', 'Sidebar', <<<'MD'
## Start here

- [[Home]]
- [[Onboarding]]
- [[How we work]]

## Shipping

- [[Deploy guide]]
- [[Feature flags]]
- [[Service map]]

## When things break

- [[On-call rota]]
- [[Incident reviews]]
- [[Incident 14 September]]

## Reference

- [[Glossary]]
- [[Café crème]]
MD, 'Created page'],

    ['32 days', 'Maya', 'Home', <<<'MD'
Welcome to the Halyard handbook: how we build, ship and support Halyard.

New here? Start with the [[Onboarding]] checklist.
MD, 'Created page'],

    ['31 days', 'Maya', 'Onboarding', <<<'MD'
Your first week, in order. Tick things off as you go.

- [ ] Get access to GitHub, the error tracker and `#ship-it`
- [ ] Read [[How we work]]
- [ ] Read the [[Deploy guide]], then ship a one-line change on day one
- [ ] Pair with whoever is on the [[On-call rota]] for an afternoon
- [ ] Skim the [[Glossary]]; nobody expects you to remember it

Questions go to your onboarding buddy, or straight into `#help`.
MD, 'Created page'],

    ['30 days', 'Tom', 'How we work', <<<'MD'
## Writing things down

If you explained something twice, it belongs in this wiki. Link generously: a link to a page that doesn't exist yet shows in red, and it's an invitation for whoever knows the answer.

## Meetings

- **Monday planning**, 30 minutes. We pick the week's goals, not tasks.
- **Thursday demo**, open to the whole company. Show something real, even if it's half done.

Everything else is async by default.

## Reviews

Review for correctness first, then clarity. Style is the linter's job. Approve with comments when the comments are small.
MD, 'Created page'],

    ['28 days', 'Tom', 'Deploy guide', $deployV1, 'Created page'],

    ['27 days', 'Inès', 'Glossary', <<<'MD'
| Term | Meaning |
|------|---------|
| **Ledger** | The append-only record of every money movement. Never updated, only added to. |
| **Mooring** | A customer workspace. One company can have several. |
| **Halyard run** | One execution of a scheduled billing job. |
| **Reissue** | Cancelling an invoice and issuing a corrected one with a new number. |
| **Ship-it** | The channel where every deploy is announced. |

Missing a term? Add it. See also the [[Service map]].
MD, 'Created page'],

    ['26 days', 'Kenji', 'Service map', <<<'MD'
Halyard is a Laravel monolith with three supporting services.

| Service | Owns | Runs on | Pager |
|---------|------|---------|-------|
| `app` | Web, API, admin | 6 web nodes | Platform |
| `billing` | Invoices, the [[Glossary\|ledger]] | Queue workers | Payments |
| `search` | Full-text search | Meilisearch | Platform |
| `mailer` | Outgoing email | Queue workers | Growth |

```php
// Every service reports health the same way:
Route::get('/health', fn () => ['ok' => true, 'version' => config('app.version')]);
```

Deploys go out the same way for all of them, see the [[Deploy guide]].
MD, 'Created page'],

    ['21 days', 'Kenji', 'Deploy guide', $deployV2, 'Add flags and post-deploy checks'],

    ['20 days', 'Maya', 'Café crème', <<<'MD'
The office machine makes one thing well. Here is how to get it.

1. Grind 18 g, fine but not powder.
2. Pull for 28 seconds.
3. Top with 120 ml of hot water. Not boiling.

Page titles can be in any language; this one lives at `/café-crème`.
MD, 'Created page'],

    ['14 days', 'Kenji', 'Incident 14 September', $incidentV1, 'Created page'],

    ['13 days', 'Kenji', 'Incident 14 September', $incidentV2, 'Add follow-ups'],

    ['12 days', 'Inès', 'Incident reviews', <<<'MD'
We write up every incident of severity 1 or 2 within two working days. Reviews are blameless: we look for what made the mistake easy, not who made it.

## Past reviews

- [[Incident 14 September]]: VAT rounding on invoices

## Template

Copy this into a new page:

```markdown
**Status:** · **Severity:** · **Lead:**

What happened, in one paragraph.

## Timeline
## What we're changing
```
MD, 'Created page'],

    ['5 days', 'Tom', 'Deploy guide', $deployV3, 'Add rollback steps and queue check'],

    ['2 hours', 'Maya', 'Home', <<<'MD'
Welcome to the Halyard handbook: how we build, ship and support Halyard. Everyone can edit; if something is wrong or missing, fix it.

## Start here

New here? Work through the [[Onboarding]] checklist, then read [[How we work]].

## Shipping

- [[Deploy guide]]: from branch to production, and back again
- [[Service map]]: what runs where, and who gets paged
- [[Incident reviews]]: what went wrong, and what we changed
- [[On-call rota]]: who to call tonight

## Teams

| Team | Lead | Channel |
|------|------|---------|
| Platform | Kenji Mori | `#platform` |
| Payments | Inès Laurent | `#payments` |
| Growth | Tom Reyes | `#growth` |

## Also useful

The [[Glossary]] decodes our jargon. The [[Café crème]] page decodes the coffee machine.

> If you explained something twice, it belongs here.
MD, 'Reorganise around what people look for'],
];
