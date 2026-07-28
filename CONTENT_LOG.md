# Content log

A running record of content and structure changes made outside of code — menu links, aliases,
node retirements, Canvas page edits — so that a future re-run of the D7 migration can be
reconciled against what has happened since.

**Why this exists:** the migrations in `web/modules/custom/mcc_migration/migrations/` are
re-runnable with `--update`, and doing so overwrites node fields from the D7 source. Anything
recorded here as "edited in D11" will be *lost* on a re-import unless the migration is fixed
first. Anything recorded as "retired" will come *back*.

Newest entries at the top. Each entry: what changed, why, and what a re-import would do to it.

---

## 2026-07-28 — IA rebuild

### Menus rebuilt for the new IA

Primary nav is five flat items plus a Get Involved CTA. Missions is deliberately **not** in the
top bar — missions is a ministry, so it lives under `/ministries`.

```
[logo]  I'm New  About  Ministries  Sermons  Calendar        [ Get Involved ]
```

- **`main`** — the five nav items. `mcc_theme.theme` now reads `main` instead of `header-nav`.
  There were two competing primary menus: `main` was what `/admin/structure/menu` put in front
  of an editor, but the theme rendered `header-nav`, so editing the obvious one did nothing.
- **`header-cta`** — `Give → /donate` became `Get Involved → /get-involved`.
- **`footer-connect`** — the Events link pointed at `/calendar/print-monthly`, **a 404**
  (the real routes are `/calendar` and `/calendar/print`). Now `/calendar`, plus Sermons,
  Ministries, Get Involved, Give and News.
- **`footer-organization`** — address and service times as `<nolink>` text, plus I'm New and
  Contact.
- **`footer-support`** — email and phone. Dropped the `mechanicsburgchristian.com` self-link.
- **Deleted `header-nav`, `footer`, `footer-programs`, `footer-resources`** — nothing rendered
  any of them. `footer` held Privacy policy, Terms of service and two duplicate Cookie settings
  links, all pointing at `/`.

Verified: all nine menu targets return 200, and both header and footer render the new links.

**Not migration-related** — menu links are content (`menu_link_content`), unaffected by a
re-import.

### Canvas page aliases — `/donate` → `/give`

**Gotcha worth knowing:** setting `path` on a `canvas_page` *inserts* a second `path_alias` row
instead of updating the existing one. `/page/1` briefly had **both** `/donate` and `/give` live.
`redirect.auto_redirect` hooks `redirect_path_alias_update()`, which never fires on an insert,
so no 301 was created — and a live alias shadows a redirect anyway, since redirects are matched
only after inbound path processing has resolved the alias. Nodes do not behave this way;
pathauto updates their alias in place and the 301 appears on its own.

`scripts/canvas-realias.php` handles this: delete the stale alias, then write the 301 by hand,
pointing at `/page/N` rather than the new alias so it survives future slug changes.

Verified: `/donate` → 301 → `/give`.

### New Canvas page — `/im-new` (id 11)

Created as a duplicate of `/get-involved` (id 3), which already carries real MCC copy, so the
new page starts from MCC structure rather than CareSphere demo content. **Its copy is still
get-involved's and needs rewriting** — see the outstanding work below.

**A re-import will not touch this.** Canvas pages are not produced by any migration; the D7
source for the intended copy is node 2 ("I am New Here", 639 chars).

### Migration: `mcc_page` re-imported with `--update`

Commit `cd4c1d3` fixed `mcc_page.yml` to map D7 `body` into `field_content` (the `page` bundle
has no `body` field, so the old `body: body` mapping resolved to NULL and was skipped silently).
That fix had never been applied to the existing rows — `migrate_map_mcc_page` showed every row
except nid 3 last imported 2026-07-25, *before* the fix.

```
ddev drush migrate:import mcc_page --update
→ Processed 25 items (0 created, 22 updated, 3 failed, 0 ignored)
```

`node__field_content` for bundle `page`: **2 rows → 16 rows.** Recovered content includes
About Christ (15k chars), Our History (11k), About Baptism (4.7k), About Salvation (3.5k).

**Re-import safe.** This *is* the migration; re-running it reproduces this state.

### FIXED — D7 nid collision between `mcc_page` and `mcc_ministry`

The 3 failures above are D7 nids **8 (Youth), 11 (Worship Service), 1099 (Youth)**. These are
type `page` in D7, but they already exist in D11 as `ministry` nodes, so the page insert hits a
duplicate-primary-key error.

Cause: `mcc_calendar_event.yml:76` does a `migration_lookup` against `mcc_ministry` for event
→ ministry references. D7 events referenced nodes 8, 11 and 1099 as ministries even though
those nodes are type `page`. Migrate therefore created **stub** ministry nodes at those nids —
visible in `migrate_map_mcc_ministry` as `source_row_status = 1` (NEEDS_UPDATE) rows for 8, 11
and 1099 that the migration's own source (`node_type: ministry`, 5 rows) never produced.

D7 content at those nids:

| nid  | D7 type | Title           | D7 body | Verdict                                        |
| ---- | ------- | --------------- | ------- | ---------------------------------------------- |
| 8    | page    | Youth           | 2,881 c | Real Youth ministry content — keep as ministry |
| 11   | page    | Worship Service | 419 c   | Sunday-morning content — keep as ministry      |
| 1099 | page    | Youth           | NULL    | Empty duplicate — retire                       |
| 1223 | ministry| Youth           | 0 c     | Empty duplicate — retire                       |

D7 modelled these as pages, but they functioned as ministries (D7's main menu had node/8
"Youth" as a top-level item; the sidebar had node/11 "Worship Service" as a section head).
Keeping them as `ministry` is the correct call and matches the new IA.

**Why the nids were unreclaimable:** the stub nodes had been deleted, but **`trash` soft-deletes** —
the rows stay in `node` / `node_field_data` with a `deleted` timestamp set (1785024865). They
were invisible to `Node::load()` and `entityQuery` but still occupied nids 8, 11 and 1099, so
every `mcc_page` insert hit a duplicate-primary-key error. This is the same Trash gotcha that
has bitten this project before.

**Fixed:**

- `mcc_page.yml` — `skip_on_value` on nids 8, 11, 1099.
- `mcc_ministry.yml` — `skip_on_value` on nid 1223 (a third, empty "Youth" that duplicated
  node 8 and was stealing the `/ministries/youth` slug).
- **New `mcc_ministry_page.yml`** — imports D7 pages 8 and 11 into the `ministry` bundle.
- Purged the three soft-deleted stubs and their stale `migrate_map_*` rows.
- Deleted node 1223 and its alias, then regenerated node 8's alias to reclaim
  `/ministries/youth`.

**Re-import safe.** `migrate:import mcc_page --update` now reports **0 failed, 3 ignored**
(down from 3 failed), and the whole `mcc` group reproduces this state from scratch.

Six ministries with clean slugs: `/ministries/youth`, `/worship-service`, `/building-grounds`,
`/care-christians-are-reaching-everyone-ministry`, `/womens`, `/missions`.

### FIXED — `ministry` and `missions` bundles dropped all D7 body text

`mcc_ministry.yml` maps `body: body`, but the `ministry` bundle has **no `body` field and no
`field_content` field** — only `field_verse`, `field_verse_link`, and four entity references.
Same failure mode as the `page` bug in `cd4c1d3`: the mapping resolves to NULL and
`EntityContentBase::updateEntity()` skips it without an error.

Silently dropped D7 content:

| nid  | Title                | Chars |
| ---- | -------------------- | ----- |
| 40   | Missions             | 6,480 |
| 31   | Building & Grounds   | 5,684 |
| 35   | C.A.R.E.             | 3,687 |
| 38   | Women's              | 3,191 |

This is why `/ministries` rendered titles and theme scripture but no descriptive text.
`missions` had the identical problem (289 + 172 chars).

**Fixed:**

- Added `field.field.node.ministry.field_content` and `field.field.node.missions.field_content`
  (reusing the existing `field.storage.node.field_content`, so no new storage).
- `mcc_ministry.yml` and `mcc_missions.yml` now use the same
  `field_content/value: body/0/value` + `static_map` format mapping as `mcc_page.yml`.

Recovered on re-import:

| Bundle   | field_content rows | Characters |
| -------- | ------------------ | ---------- |
| page     | 16                 | 42,478     |
| ministry | 6                  | 22,342     |
| missions | 2                  | 461        |

**Re-import safe.**

### Alias structure — `pathauto.pattern.menu_path` deleted

`menu_path` (`/[node:menu-link:parents:join-path]/[node:title]`) had **empty selection
criteria**, so it applied to every bundle and tied each node's URL to its menu placement.
Any menu work would have silently rewritten bio, ministry, missions and event aliases. Deleted
and replaced with explicit per-bundle patterns:

| Pattern               | Bundle           | Pattern                                                            |
| --------------------- | ---------------- | ------------------------------------------------------------------ |
| `bio_path`            | `bio`            | `/about/leadership/[node:title]`                                     |
| `ministry_path`       | `ministry`       | `/ministries/[node:title]`                                           |
| `missions_path`       | `missions`       | `/ministries/missions/[node:title]`                                  |
| `calendar_event_path` | `calendar_event` | `/calendar/[node:field_mission_category:entity:name]/[node:title]`   |
| `page_content`        | `page`           | `/[node:title]` (unchanged; utility pages get manual IA slugs)       |

Aliases regenerated for bio (20), ministry (6) and missions (2) — **25 301 redirects minted
automatically** by `redirect.auto_redirect`, which is on with `default_status_code: 301`.

**The 892 calendar events were deliberately NOT regenerated.** The new pattern applies to any
event saved from now on; existing events keep their flat aliases, and the legacy redirect
migration will cover the old URLs. Regenerating them would churn 892 aliases and mint 892
redirects for cosmetic gain. Smart Date's `[node:field_event_date:value]` token returns a raw
timestamp rather than a formatted date, which is why the pattern uses the mission category
rather than the event date. 864 of 892 events have a category; the 28 without collapse cleanly
to `/calendar/[title]`.
