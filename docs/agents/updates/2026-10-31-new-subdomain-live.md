# 2026-10-31 — `new.mechanicsburgchristian.com`, mcc2026 test and live, the tip hand-off

## What was done

- **Found where the domain's DNS lives:** eNom, which is both the registrar and the DNS host
  (`dns1`–`dns5.name-services.com`). The apex and `www` point at the D7 site on Pantheon. Details
  are in [domains.md](../domains.md).
- **mcc2026 moved from sandbox to Basic** (by the user, 20:17 UTC), the plan custom domains need.
- **The 2026-10-24 tip hand-off ran on `mcc2026.dev`**, after step 0 confirmed the tip was untouched
  (953 / 1623 / 2026-09-05). Backup `mcc2026_dev_2026-10-31T20-39-15_UTC_database.sql.gz` came first,
  then files up (12 MB new), the `db-post-flyers-2026-10-24` dump in, and `drush deploy`. Dev now has
  1012 nodes, newest 1682, and empty `updatedb:status` and `config:status`. All four check scripts
  were no-ops. `homepage-structure` always re-saves, but the tree came out the same.
- **`test` and `live` created** from dev (`env:deploy`, which clones the database and files on
  first creation), then **`new.mechanicsburgchristian.com` added to live**.
- **`drush/drush` moved to `require`** (`9bcf04e`), because test and live are built without dev
  packages and had no drush. Promoted to both, then `drush deploy` on each. Both match dev:
  1012 nodes and no config drift.

## Rules added or changed

- [domains.md](../domains.md), a new topic: the wildcard AAAA means a subdomain needs its A and
  both AAAA records added explicitly; ownership verification by TXT; Basic's five domains; the
  custom domain is crawlable.
- [deploys.md](../deploys.md): live is promoted by hand and its database is separate from dev's;
  drush belongs in `require`.

## Left open

- **The trial lock is still on.** Every environment still answers `504 Target in maintenance`, and
  `terminus lock:info` shows the lock Pantheon set on the sandbox (user
  `Disabled_due_to_spam_abuse`) on dev and live, nearly an hour after the plan change. It was left
  for Pantheon or the user to lift, not removed from here.
- **DNS records at eNom**, to be added by whoever holds the eNom login. The host names are as
  eNom's editor wants them:

  | Host | Type | Value |
  | ---- | ---- | ----- |
  | `_acme-challenge.new` | TXT | `MFXyXROWDOzrKnHpoMwBIDmsiLF1EzTMh0sXqVptp4A` |
  | `new` | A | `23.185.0.1` |
  | `new` | AAAA | `2620:12a:8000::1` |
  | `new` | AAAA | `2620:12a:8001::1` |

  Then run `ddev exec terminus domain:verify mcc2026.live new.mechanicsburgchristian.com`, and
  check `terminus domain:dns mcc2026.live` and `https:info` until everything reads OK.
- **Which environment is the tip is undecided.** All docs and scripts still treat `mcc2026.dev`
  as the source of truth. Live is a copy from 20:52 UTC today. An edit made at
  `new.mechanicsburgchristian.com` is lost the next time dev's content is cloned to live, and an
  edit made on dev never shows there until it is.
- **Search indexing:** `new.` will be crawlable once it resolves (see domains.md).
- `composer` reported 10 advisories against 3 packages during the re-lock. Not looked at; the
  next dependency update starts with `composer audit` anyway.
