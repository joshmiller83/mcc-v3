# 2026-10-24 — D7 calendar sync, PHP 8.5 and the October advisories, docs split

## What was done

- **The calendar was synced from the live D7 site** (`mcc-church.live`) into the local `db`:
  two new events, six D7 edits, three nodes the office deleted retired with 301s, two new
  homepage teasers. Every node edited on both sides was settled — four by a field-level
  merge, the rest by the user keeping the tip's version. The full record, the checks and
  both cutoffs for the next sync are in `CONTENT_LOG.md` under 2026-10-24.
- **PHP 8.5.12** locally (`.ddev/config.yaml`, plus `.ddev/web-build/Dockerfile.php-security`
  for the patch release) and on Pantheon (`pantheon.yml`). The tip already ran 8.5.12.
- **`composer update`**: 55 updates, `composer audit` from twelve advisories to none — Drupal
  core 11.4.8 → 11.4.11 (SA-CORE-2026-014, a critical remote code execution, and two XSS),
  UI Icons 2.1.0, Gin 5.0.16, Easy Breadcrumb 2.0.11, Menu Link Attributes 1.10.0,
  svg-sanitize 1.1.1, Symfony 7.4.21. Seven update hooks; one new config file.
- **AGENTS.md split** into a short index, the topic files in `docs/agents/`, and this folder.
  The sections moved verbatim; only cross-file links were repointed.

## Rules added or changed

- [d7-migration.md](../d7-migration.md) — compare both-sides edits field by field, since a body
  diff can be a span the text format strips; D7 deletions never arrive on their own, so find
  them through the maps; the D7-side cutoff is the copy's newest change, not the import time;
  keep before/after tables out of `db`. The 1177 note was wrong (the 6 PM was D7's edit).
- [dependency-updates.md](../dependency-updates.md) — PHP security releases (Pantheon applies
  them, ddev needs `ddev utility rebuild`); start with `composer audit`; the three PHP 8.5
  deprecation notices that are expected; lint with the new PHP before switching.
- [tooling.md](../tooling.md) — `< /dev/null` on every SSH command inside a heredoc; Playwright
  probes go in `.ddev/.downloads/`; the host's `python3` has no `json`, use `jq`.
- [content-scripts.md](../content-scripts.md) — `scripts/retire-d7-deleted.php` joins the list.
- [announcements.md](../announcements.md) — the 10-01 hand-off recipe is replaced by the one
  below; it never copied the media files to the tip.

## Tip hand-off

The tip (mcc2026.dev) has had neither the 10-01 nor this sync. Its web tier answered
`504 Target in maintenance` all day; drush, SSH and code deploys all worked. Nothing has been
edited on it since 2026-09-05 — 953 nodes, newest nid 1623, newest change
2026-09-05 14:35:52 UTC — so the local `db` is a strict superset and nothing on the tip is lost
by replacing it.

`.ddev/.downloads/db-post-sync-2026-10-24.sql.gz` (gitignored): `drush sql:dump`, completed
2026-10-24 14:35:57 UTC, 2.7 MB, 251 tables, sha256 `11a00dae07be…59768defed6`. It holds both
syncs, the announcements, the dedupe, the retirements and the 2026-10-24 schema and config.
The cache, session, watchdog, semaphore, flood, history and queue tables go over empty
(`--structure-tables-list`), so no local cache paths or login sessions travel with it. It
supersedes `db-post-announcements-2026-10-01.sql.gz` and `db-post-sync-2026-10-01.sql.gz`.

Once the tip answers, in this order:

```sh
# 0. Confirm nothing was edited there since this was written — expect 953, 1623, 2026-09-05.
ddev exec terminus drush mcc2026.dev -- sql:query \
  "SELECT COUNT(*), MAX(nid), FROM_UNIXTIME(MAX(changed)) FROM node_field_data" < /dev/null
#    If it was, stop: those edits are lost by step 3. Pull and compare first (d7-migration.md).

# 1. Code: already there — every push to main deploys it.

# 2. Files. The dump carries media entities, not their files: the D7 images from both syncs
#    and the five announcement crops are not on the tip. Never --delete.
ddev exec bash -s <<'EOF'
U=$(terminus connection:info mcc2026.dev --field=sftp_username < /dev/null)
H=$(terminus connection:info mcc2026.dev --field=sftp_host < /dev/null)
rsync -rltz --ipv4 -e 'ssh -p 2222' --exclude=css/ --exclude=js/ --exclude=php/ --exclude=styles/ \
  web/sites/default/files/ "$U@$H:files/" < /dev/null
EOF

# 3. The database — take a backup first, it is the undo.
ddev exec terminus backup:create mcc2026.dev --element=db
zcat .ddev/.downloads/db-post-sync-2026-10-24.sql.gz \
  | ssh -p 2222 dev.<site-id>@appserver.dev.<site-id>.drush.in drush sql-cli

# 4. updatedb and config:import are no-ops (the dump was taken after both); the cache rebuild
#    is not, because the caches went over empty.
ddev exec terminus drush mcc2026.dev -- deploy -y

# 5. Checks. Each script is a no-op if the dump landed, and says so.
for s in homepage-structure retire-d7-deleted calendar-dedupe-womens-bible-study announcements-content; do
  ddev exec terminus drush mcc2026.dev -- php:script scripts/$s.php < /dev/null
done
ddev exec terminus drush mcc2026.dev -- updatedb:status < /dev/null
ddev exec terminus drush mcc2026.dev -- config:status < /dev/null
curl -s -o /dev/null -w '%{http_code}\n' https://dev-mcc2026.pantheonsite.io/calendar
```

`<site-id>` is in `terminus connection:info mcc2026.dev`. The SSH key and `ddev auth ssh` are
needed for steps 2 and 3 (see [tooling.md](../tooling.md)).

## Left open

- **For the church**, all detailed in `CONTENT_LOG.md`: the Sunday entries' wording and the
  Facebook Live entry differ from the live site by choice; 1154 Highway 52 Youth Groups is
  ended here and continues in D7; 526 Finance has no meeting on 2026-10-26 on either site;
  1625 Women's Bible Study opens with a single Tuesday.
- **The two new announcements are Word-page flyers** (1275×1650, no body), unreadable on the
  front page, like the five rebuilt on 10-01. Not rebuilt; `scripts/announcements-content.php`
  is the pattern if the church wants them done.
- **Canvas throws during cron**: `RuntimeException: It is impossible for an item to exist in
  multiple Folders.`, three times since 2026-10-03. Not caused by either sync; not looked into.
- **The live site is Drupal 7**, end of life since January 2025, so it gets no security
  releases at all. This month's core advisories name only Drupal 8.9 and later as affected,
  but nothing about the next one will reach D7 either.
