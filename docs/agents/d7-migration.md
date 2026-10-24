# The D7 migration

_Part of the agent guidance indexed in [AGENTS.md](../../AGENTS.md). Read it before working on this area._

`mcc_migration` reads the `legacy` database (a copy of `mcc-church.live`, see
[Refreshing local from both sites](environment.md#refreshing-local-from-both-sites)) and writes into this site.
It can only run **locally** — Pantheon has no `legacy` database — so its result reaches
`mcc2026.dev` by pushing the local database up. D7 is still the church's live site and its office
still edits it, so this is a recurring sync, not a one-off.

- **The tip is not a passive downstream any more, and a blanket `--update` destroys work on it.**
  On 2026-08-28 and 2026-09-05 someone edited the calendar directly on `mcc2026.dev`: three events
  created there, eight migrated ones edited (the Adult and Youth services merged into single
  entries, the separate Facebook Live series ended, bodies trimmed). `migrate:import --group=mcc
  --update` rewrites every mapped row from D7 whether or not D7 changed it, so it silently
  reverted all eight. None of it was in `CONTENT_LOG.md`, so nothing warned of it.
- **No migration pins a destination id, and none may.** They used to (`nid: nid`, `fid: fid`,
  `tid: tid`), on the theory that this made a re-import update in place. It doesn't — the
  **migrate map** does that, and always did. What the pin actually does is this: for a source row
  with *no* map row yet, `EntityContentBase::getEntity()` takes the pinned id, finds an entity
  already sitting at it, and **loads and overwrites it** instead of creating one. Both sites mint
  ids from the same low range, so the first time D7's counter reached an id this site had already
  used, D7's "Youth Sunday School" replaced this site's "MCC Serves at Shalom" (nid 1623), and five
  D7 teaser images replaced the homepage hero placeholder and four About card icons (fids 1781,
  1786–1789). Terms were next: D7's next tid is 81, which here is the first Ministry Group.
  Every cross-reference already resolves through `migration_lookup`, so nothing depended on the
  ids matching. Where `nid:` doubled as a `skip_on_value` row filter (`mcc_page`, `mcc_ministry`,
  `mcc_ministry_page`), the filter now hangs off a `_row_filter` pseudo-field — `skip_on_value`
  returns the value it was given, so keyed as `nid` it was a pin too.
- **Import new rows plainly, and `--update` only the rows D7 actually edited.** `track_changes`
  isn't set, which is why `--update` was used before — but D7's `node.changed` already says which
  rows were edited, so the blanket isn't needed:

  ```sh
  ddev drush migrate:import --group=mcc                 # new rows only; seconds, not ten minutes
  ddev mysql -e "SELECT nid, title, FROM_UNIXTIME(changed) FROM legacy.node
    WHERE type='calendar_item' AND changed > UNIX_TIMESTAMP('<last import, UTC>') ORDER BY nid"
  ddev drush migrate:import mcc_calendar_event --update --idlist=<those nids, minus any edited here>
  ```

  Terms, users and files have no `changed` column in D7; compare them through the map instead
  (`legacy.taxonomy_term_data.name` against `taxonomy_term_field_data.name` joined on
  `migrate_map_mcc_taxonomy`). Done this way no bio, page or ministry is rewritten, so the
  thirteen post-import scripts have nothing to repair — they were run anyway on 2026-09-21 and a
  before/after diff of titles, status, aliases, Canvas trees and menus came back identical.
- **Before any `--update`, find what was edited here.** Load the tip's dump into a scratch database
  and list nodes whose `changed` is later than the last import:
  `ddev import-db --database=tip_before --file=.ddev/.downloads/db.sql.gz`. A node on both lists —
  edited here *and* in D7 — is a real conflict; its first revision on the tip is the common base
  for a three-way compare (`node_revision__field_*` by `revision_id`). An `--update` does not mint
  a revision, so that first revision really is the imported state. Date conflicts on a real
  congregation's calendar are the church's call, not an agent's.
- **Do that compare field by field; "edited on both sides" is not yet a conflict.** On
  2026-10-24 four of the eight both-sides nodes came apart cleanly: the tip's 09-05 body edits on
  526, 1154 and 1177 were each the removal of one `<span style="font-size: large;">` wrapper
  (exactly 39 characters shorter), and `content_format` strips `<span>` and `style` at render
  time anyway, so D7's body and the tip's render identically. Where the tip touched only such
  a body and D7 only the dates, `--update` loses nothing visible. Compare per field (title,
  status, body hash and length, picture, category, and the occurrence set — count, first,
  last, times) between base, tip and D7 before calling anything the church's choice, and
  check the format's `allowed_html` before treating a body diff as content.
- **D7 deletions never reach this site on their own.** A migration only creates and updates,
  so a node the office deletes in D7 stays published here. Find them through the maps —
  `migrate_map_<migration>` rows whose `sourceid1` has no `legacy.node` row — after every
  pull; a D7 node count that rose by less than the number of new nids is the tell (1,022 →
  1,023 with four added, on 2026-10-24). Retire the ones to follow with
  `scripts/retire-d7-deleted.php`, which declares each node and where its URL should land.
- **The D7-side cutoff is the newest `changed` in the copy the last import read**, not the
  import's own start time: anything the office saved between the dump and the import is in
  neither. The two cutoffs differ, and `CONTENT_LOG.md` records both from 2026-10-24 on.
- **Two scripts make that list noisy.** `mcc_split_bio_name_role.php` re-saves Jon Culbertson (346)
  and `ia-page-slugs.php` re-saves the nine retired pages on every run. No value changes, but
  `changed` moves, so they show up as "edited on the tip" on the day the scripts last ran.
- **Both sites entering the same event makes a duplicate, and nothing catches it.** "Women's Bible
  Study" was created here on 08-28 and in D7 on 09-16; after the import both were published and two
  Wednesdays showed it twice. Check with a `GROUP BY TRIM(title), field_event_date_value HAVING
  COUNT(DISTINCT nid) > 1` over `node__field_event_date`. (The "Christmas Day" pair, 1330/1331, is an
  old duplicate in D7 itself.) The Women's Bible Study pair was settled on 2026-10-03 by
  `scripts/calendar-dedupe-womens-bible-study.php`: the tip-created 1621 unpublished, its alias
  301'd to the D7 import 1625. The script aborts unless the survivor exists and is published, so
  on an environment that has not received the 10-01 sync it does nothing — run it there *after*
  the dump push, never before.
- **`migrate:status` prints "Last Imported" in the site's timezone, not UTC.** `10:36` there was
  `14:36` UTC. Every `FROM_UNIXTIME()` in the ddev database is UTC, so a cutoff copied straight from
  `migrate:status` lands four hours early and sweeps the import's own writes into "edited since".
- **Read the cutoff from the destination database, never from a plan, a log or memory.** The
  09-21 import ran locally and was never pushed to the tip; `scripts/sync-config-from-tip.sh`
  then replaced the local `db` with the tip's on 2026-10-01, and the "last import" the day's
  plan carried (09-21 22:24 UTC) was a month later than the one the database had actually seen
  (08-21 14:36 UTC). Used as written it would have left three D7 edits behind and hidden three
  real conflicts. `migrate:status` shows the time in site time; per migration in UTC it is
  `SELECT name, FROM_UNIXTIME(CAST(SUBSTRING_INDEX(SUBSTRING(value,3),';',1) AS UNSIGNED) DIV 1000)
  FROM key_value WHERE collection='migrate_last_imported'` — the values are PHP-serialised
  milliseconds, `d:1787323014915;`. Each sync's entry in `CONTENT_LOG.md` now ends with the
  cutoff for the next one.
- **A zero-row `mcc_bio` import still fires its `POST_IMPORT` subscribers.** `BioDuplicateMerger`
  re-saves Jon Culbertson (346) on every pass, so his `changed` moves and he lands on the
  "edited on the tip" list — and it disagrees with `mcc_split_bio_name_role.php`: the split
  script empties `field_role` on 346 (his own title carries no " - Role"), the merger refills it
  with "Finance" from record 1604, and whichever ran last wins. The tip showed it blank from
  08-21; after the 10-01 sync it reads "Finance" again. The same pass retires 1604's regenerated
  alias into a redirect, and the focal-point conversion runs over any new image. Expect those
  three notices in the watchdog after any import; they are not errors.
- **"Months that gained an event" is a node × month diff, and one D7 edit can make it sixty.**
  Take the (nid, YYYY-MM) pairs from `node__field_event_date` before and after the import and
  keep the difference; "months with any occurrence of a touched node" is wrong for an edit that
  only changed a body. On 2026-10-01 a D7 edit turned "September 11/Patriot Day" from one
  occurrence into fifty yearly ones through 2075, so the honest list was 61 months.
  `scripts/calendar-compare.mjs` takes them all as repeated `--month` flags; run the whole list
  rather than trimming it by hand.
- **Keep before/after tables in a scratch database, never in `db`.** On 2026-10-24 the pairs
  went into `sync_before`/`sync_after` inside `db`; having no primary key, they turned the
  status report's "Transaction isolation level" check into an Error, and they would have ridden
  into the hand-off dump. `CREATE DATABASE` as root (`mysql -uroot -proot`) and `GRANT ALL … TO
  'db'@'%'`, the same way `legacy_1001` held the previous D7 copy for that day's diff.
- **The import log is 4,000+ lines of one harmless warning.** Core's `d7_file` source reads
  `constants.source_base_path`, which these migrations don't declare, once per row: `Undefined array
  key "constants" File.php:105`. It changes nothing, but it buries real errors — read the log with
  `grep -vE "File.php:105" | grep -E "done with|\[error\]|Exception|SQLSTATE"`, never with `tail`.
- **To confirm a month against the live D7 site, diff occurrences by D7 nid, in UTC, and
  expect the tip's own edits to show up as differences.** Both databases store UTC — D7's
  `field_data_field_date` as `datetime`, this site's `node__field_event_date` as a timestamp —
  so compare `FROM_UNIXTIME(field_event_date_value)` against `field_date_value` directly, and
  key on the D7 nid through `migrate_map_mcc_calendar_event` (`destid1` → `sourceid1`), never
  on the title: a tip-side retitle is not a date change. Read D7 straight from the live site
  (`terminus drush mcc-church.live -- sql:query …` over `ddev exec bash -s <<'EOF'`, after
  `ddev auth ssh`) rather than from `legacy`, which is only as fresh as the last pull; a
  `changed > <copy's newest change>` query on `legacy.node`'s live counterpart says whether the
  copy is current. Done for October 2026 on 2026-10-03: 41 of 42 local occurrences identical
  to live, and every difference was one of the 2026-09-05 tip edits already in
  `CONTENT_LOG.md` — the 1553/1554 and 1325/1523 merges, the 1500 Sundays removed on the tip,
  and the 1177 time. A mismatch on that list is the church's unresolved choice, not a
  migration bug; anything *off* that list is. (The 1177 hour turned out to be a D7 edit the
  tip never made — the tip still had the imported 7 PM — and was taken from D7 on 2026-10-24.
  The choices still standing are in that day's `CONTENT_LOG.md` entry.)
- **`homepage_teaser` is migrated, as the `announcement` type** (`mcc_announcement`, since
  2026-10-01). It was skipped before on the theory that the Canvas front page had no place for the
  teasers; it does now, the `home-announcements` band — see [Announcements](announcements.md). A new
  D7 teaser comes in with the plain `migrate:import --group=mcc`. A `--update` of this migration
  hands the five rebuilt announcements their flyers and empty bodies back (`CONTENT_LOG.md`,
  2026-10-01), so re-run `scripts/announcements-content.php` after one.

