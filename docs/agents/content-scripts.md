# Content scripts: content lives in the database

_Part of the agent guidance indexed in [AGENTS.md](../../AGENTS.md). Read it before working on this area._

Helper scripts, all idempotent and safe to re-run — which matters, because a migration
re-import re-applies the flat `/[node:title]` pattern and undoes the slugs:

- `scripts/ia-page-slugs.php` — IA slugs for the D7 utility pages, plus retirements.
- `scripts/ia-landing-copy.php` / `scripts/ia-landing-links.php` — landing page copy and CTAs.
- `scripts/canvas-realias.php` — move a Canvas page and 301 its old URL.
- `scripts/canvas-swap-component.php` — swap a component in a Canvas page's tree.
- `scripts/homepage-structure.php` — the front page's band tree plus the four
  photo-slot media entities.
- `scripts/footer-menus.php` — the four footer column menus, declared in full.
- `scripts/ministry-groups.php` — the Ministry Groups terms, their order and copy.
- `scripts/ministries-content.php` — the five `page`→`ministry` bundle conversions,
  plus every field value on all thirteen ministries and partners.
- `scripts/ministries-cleanup.php` — Word/D7 residue out of the ministry bodies, the
  time-commitment lift, the Youth calendar event repoint, and the duplicate aliases.
- `scripts/ministries-page.php` — the /ministries Canvas band tree.
- `scripts/ia-page-slugs.inc.php` — `mcc_retire_stale_aliases()`, shared by every
  script that moves a URL. Include it; don't copy it. Its third argument is the nid the
  redirects should land on, for a node retired in favour of another one.
- `scripts/calendar-dedupe-womens-bible-study.php` — unpublishes the tip-created
  duplicate of the Women's Bible Study series in favour of the D7 import.
- `scripts/retire-d7-deleted.php` — unpublishes the nodes whose D7 source the office
  deleted, each declared with where its old URL should 301 to.

**Content lives in the database; only code is deployed by git.** A push to `main`
deploys code to mcc2026, and does *nothing* to that environment's Canvas trees, media
or menus. So a content change has to be expressed as an idempotent script here and
then run against each environment:

```sh
ddev drush php:script scripts/<name>.php                              # local
ddev exec terminus drush mcc2026.dev -- php:script scripts/<name>.php
```

Write these so re-running is a no-op: declare the whole desired tree, look media up
by name before creating it, and never assume the previous run's state. That is what
makes them safe to re-run after a migration re-import, which otherwise undoes them.
Ship any image a script needs inside the theme (`mcc_theme/images/…`) so it travels
with the code, and have the script copy it into the files directory.

