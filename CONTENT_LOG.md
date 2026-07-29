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

## 2026-07-29 — footer menus reshaped into four columns

The homepage design handoff's footer is a brand cell plus four link columns — **Visit ·
Ministries · Connect · About** — and `mcc_theme_preprocess_page()` reads one menu per column.
Only three footer menus existed, and one of them ("Footer Contact": an email address and a
phone number) was contact detail rather than a column. `scripts/footer-menus.php` declares all
four menus in full and is idempotent:

| Column     | Menu                  | Change                                                                    |
| ---------- | --------------------- | ------------------------------------------------------------------------- |
| Visit      | `footer-organization` | Renamed "Footer Visit" → "Visit"; gained `(765) 325-2772` (`tel:`), moved from Footer Contact |
| Ministries | `footer-ministries`   | **New menu** — the six published ministry nodes                            |
| Connect    | `footer-connect`      | Renamed "Footer Connect" → "Connect"; lost "Ministries" (now its own column); gained "Email us", moved from Footer Contact |
| About      | `footer-about`        | **New menu** — Who we are, Our beliefs, Our history, Our leadership        |

**The menu label is the column heading**, which is why the "Footer " prefix came off — renaming
a menu now renames the column. The script writes labels only on create, or on a menu still
carrying its pre-design name, so re-running it will not undo a heading someone renamed.

**`footer-support` ("Footer Contact") is deleted.** Both of its links live in other columns
now. Canvas had auto-registered it as a `system_menu_block` component; that config falls back
to disabled rather than disappearing, and is exported that way.

Every URL in the script points at a page that already exists — no destination is invented. The
street address and the service times stay `<nolink>` text, not anchors: they are facts, not
somewhere to click.

**Re-import safe** — menus are content, not touched by the migration. Re-run the script on any
environment that needs it (`ddev drush php:script scripts/footer-menus.php`), since a push to
`main` deploys code only.

---

## 2026-07-29 — new "I'm New" page for first-time visitors

Folding the old /im-new into Get Involved (below) left the nav with no entry for someone who
has never visited. **Canvas page 13, `/im-new`, "I'm New"** is that page, built by
`scripts/ia-im-new-page.php` — five bands: hero, "When we gather" (Sunday School 9:30 /
Worship 10:30 / children and youth), "Come as you are", a four-item FAQ, and address + contact.
Added back to the `main` menu at weight −10, ahead of About. The script also **deletes the
`/im-new` → Get Involved 301** that the merge created, since a live redirect fires before path
processing would ever resolve the new alias.

**Every claim on the page traces to something already on the site**, because inventing details
about a real congregation is not acceptable:

| Claim | Source |
|---|---|
| Sunday School 9:30, worship 10:30 | `footer-organization` menu |
| 650 W. Horton Road; (765) 325-2772 | `footer-organization` / `footer-support` menus |
| nursery, Kids Worship, Sunday School, youth | ministry nodes 12, 10, 13, 8 |
| streamed on Facebook Live | recurring "FaceBookLive Church Service 10:30a" calendar event |
| "welcome exactly as you are", "without much fuss" | `/about` copy |

**Not on the page, and deliberately:** where to park, which door to use, how long the service
runs, communion practice. None of it is recorded anywhere. `ia-im-new-page.php` carries a
commented-out `PARKING` constant — fill it in and re-run to add a fifth FAQ item.

**On a re-import:** canvas pages are not migrated, so page 13 survives. The script is
idempotent — it finds the page by its alias and rebuilds the component tree in full.

---

## 2026-07-29 — "I'm New" folded into Get Involved

Canvas pages **3** (`Get involved`, `/get-involved`) and **11** (`I'm New`, `/im-new`) held the
same content: same five bands, same three serve cards, same four FAQ items, differing only in
button label casing. Page 11's `h1` was already "Get Involved at MCC", so there was never any
first-time-visitor content — it was Get Involved content under the wrong name.

Page 11 is the copy that received the structural repair below, so it is the one that survives.

| What | Before | After |
|---|---|---|
| Canvas page 11 | "I'm New", `/im-new` | "Get Involved", `/get-involved` |
| Canvas page 3 | published at `/get-involved` | **unpublished**, alias dropped |
| `main` menu | I'm New · About · Ministries · Sermons · Calendar | About · Ministries · Sermons · Calendar |
| `footer-organization` | Visit column had an "I'm New" link | link removed |
| `/im-new` | page 11 | **301 → `/page/11`** |

`header-cta` and `footer-connect` already pointed at `/get-involved`, so both now resolve to
page 11 with no edit. The `/about` CTA that said "I'm new" was repointed to `/get-involved` but
**kept its label** — `/about` now has three CTAs to the same page, which is an editorial call
to make, not a mechanical one.

Run `scripts/ia-get-involved-merge.php`; it is idempotent. It deletes the old alias *before*
writing the 301, because a live alias shadows a redirect — same reasoning as
`canvas-realias.php`. The redirect points at `/page/11`, not at the alias.

**On a re-import:** canvas pages are not migrated, so pages 3 and 11 survive as they are. The
menu links are `menu_link_content` entities and also survive. Re-running
`scripts/ia-landing-links.php` is safe — it merges `href`/`label` only and preserves component
nesting. Re-running `scripts/ia-page-slugs.php` is unaffected.

### Structural repair of page 11 (same day)

All 25 of the page's components were stored with `components_parent_uuid` and `components_slot`
NULL, so Canvas rendered eight empty padded `<section>`s with their intended children emitted
as siblings after them. The `section` / `section-grid` templates were never at fault — both
print their slots. `scripts/get-involved-structure.php` rebuilds the tree by uuid (idempotent)
into five nested bands, 22 components; three sections that existed only as pseudo-containers
were dropped. Card CTAs were repointed off `/node/35` and off `/our-missions`, which was a 404.

Pages 3 and 11 were the only two Canvas pages with flat component trees; every other page
nests correctly. Page 11 is repaired and page 3 is retired, so none remain. Verify with:

```sql
SELECT entity_id, COUNT(*), SUM(components_parent_uuid IS NOT NULL)
FROM canvas_page__components GROUP BY entity_id;
```

---

## 2026-07-28 — IA rebuild

### Redirects — all of it is the `redirect` module

Everything below creates ordinary **redirect-module entities**, visible and editable by an
admin at `/admin/config/search/redirect` exactly like a hand-made one. Nothing uses a bespoke
redirect mechanism. Two things do the work:

- **`redirect.auto_redirect: true`** (already on, `default_status_code: 301`) mints a 301
  automatically whenever a node's alias is *updated in place* — which is what pathauto does.
  25 redirects appeared on their own when bio/ministry/missions aliases were regenerated.
- **Explicit `Redirect::create()`** for the cases auto_redirect cannot see. There are two, and
  both are the same root cause: **setting a `path` field inserts a second `path_alias` row
  rather than updating the existing one.** No update means `redirect_path_alias_update()` never
  fires, and worse, the stale alias stays live and *shadows* any redirect you add by hand,
  because redirects are matched only after inbound path processing has resolved the alias.

  So the old alias must be deleted first, then the 301 written. Both helper scripts do this and
  point the redirect at `/node/N` or `/page/N` rather than the new alias, so they survive future
  slug changes.

Redirect count: **20 → 977**.

### Legacy D7 URLs — `mcc_redirect` migration

The live D7 site publishes 1,055 aliases, 979 of them node aliases under `content/`. Those are
what Google has indexed. `auto_redirect` cannot help: nothing was renamed on *this* site, so
there is no alias update to hook — they have to be created outright.

`mcc_redirect.yml` + `src/Plugin/migrate/source/D7UrlAlias.php` do this with
`destination: entity:redirect`, so the output is **ordinary redirect-module entities**, editable
at `/admin/config/search/redirect` like any hand-made one. A migration rather than a script
because it is re-runnable, rolls back with `migrate:rollback mcc_redirect`, and resolves each
D7 nid through the existing `mcc_*` migrate maps — so it stays correct if a node lands on a
different nid in a future re-import.

Redirects point at `/node/N`, never at an alias, so they survive every future slug change.

**979 processed → 915 created, 0 failed, 64 ignored.** The 64 are the retired pages and their
descendants: `skip_on_empty` drops any row whose nid has no destination on this site, so those
old URLs 404 rather than pointing somewhere misleading.

Taxonomy (`itunes-category/*`, from a D7 podcast vocabulary that no longer exists) and `users/*`
aliases are excluded at the source query.

**Two bugs worth remembering if this is ever edited:**

1. **Plugin ID collision.** The source plugin was originally `d7_url_alias`, which is core's
   own path-module plugin — core's won silently, and the migration happily read
   `taxonomy/term` rows. It is `mcc_d7_url_alias` now.
2. **`constants:` must live inside `source:`,** not at the top level of the migration. At the
   top level it parses without error and resolves to nothing, which produced 904 redirects whose
   destination URI was a bare nid like `494`, throwing `InvalidArgumentException: The URI '494'
   is invalid` and 500ing every legacy URL.

### D7 utility pages — IA slugs and retirements

`scripts/ia-page-slugs.php` (idempotent, safe to re-run after any migration re-import — which
is the point, since `mcc_page` re-applies the flat `/[node:title]` pathauto pattern).

Aliases are pinned with `PathautoState::SKIP` so an ordinary editor save doesn't revert them.

| D7 nid | Page                        | New slug                                     |
| ------ | --------------------------- | -------------------------------------------- |
| 3      | Who We Are                  | `/about/who-we-are`                          |
| 17     | Our Foundational Beliefs    | `/about/beliefs`                             |
| 18     | About Salvation             | `/about/beliefs/salvation`                   |
| 19     | About Baptism               | `/about/beliefs/baptism`                     |
| 20     | About the Church            | `/about/beliefs/the-church`                  |
| 21     | About Christ                | `/about/beliefs/christ`                      |
| 22     | About the Bible             | `/about/beliefs/the-bible`                   |
| 101    | Our History                 | `/about/history`                             |
| 13     | Sunday School               | `/ministries/worship-service/sunday-school`  |
| 12     | Nursery                     | `/ministries/worship-service/nursery`        |
| 10     | Kids Worship                | `/ministries/worship-service/kids-worship`   |
| 1098   | Prayer & Outreach           | `/ministries/prayer-outreach`                |
| 1100   | Emergency Response (ERT)    | `/ministries/emergency-response`             |

Sunday School, Nursery and Kids Worship sit under the Worship Service ministry because they are
Sunday-morning provision, not standalone ministries — which is how D7's own sidebar had them.

**Retired** (unpublished, alias removed, **and skipped in `mcc_page.yml`**):

| D7 nid | Page              | Why                                                            |
| ------ | ----------------- | -------------------------------------------------------------- |
| 1      | Home              | D7 body was "Hello World."; the front page is Canvas page 9     |
| 2      | I am New Here     | content belongs to the `/im-new` Canvas page                    |
| 14     | Adult Classes     | NULL body in D7 — a placeholder never filled in                 |
| 15     | Childrens Classes | NULL body in D7 — ditto                                         |
| 16     | Teens Classes     | NULL body in D7 — ditto                                         |
| 29     | Ministries        | superseded by the `/ministries` Canvas landing page             |
| 41     | Our Missions      | superseded by ministry node 40 at `/ministries/missions`        |
| 189    | Ruth Sinn         | a page node duplicating bio content                             |
| 344    | Test Secure Page  | test fixture                                                    |

They are **skipped in the migration, not imported-then-unpublished**. Status comes from the D7
source, so an imported page would be republished on every re-run. Their content remains in the
`legacy` database if it is ever wanted.

`migrate:import mcc_page --update` → **0 created, 13 updated, 0 failed, 12 ignored.**

### Missions sits under Ministries

Missions is a ministry, so ministry node 40 keeps `/ministries/missions` and stays in the
ministries listing. The two individual missions are listed on it by the `mcc_missions` Views
block, placed in the `content` region with a `request_path` visibility condition rather than
being given a route.

The CareSphere "Impact" page (canvas_page 7) was **deleted** — invented stats and three
testimonials attributed to people who do not exist.

### Leadership page moved, deliberately not converted to Canvas

`mcc_leadership_groups` keeps its Views **page** display; only its path changed, from
`/who-we-are/our-leadership` to `/about/leadership`, with a 301 from the old path.

Every other landing page became a Canvas page with a Views block, but this one is a two-view
construction with a custom template (`views-view--mcc-leadership-groups.html.twig`) and
per-term render cache keys that AGENTS.md explicitly warns against disturbing. Moving the slug
achieves the IA goal at near-zero risk. Converting it to Canvas remains possible later.

### Landing page copy and links

`scripts/ia-landing-copy.php` and `scripts/ia-landing-links.php` — both idempotent, both match
components by uuid. The copy lives in version-controlled scripts rather than only in the
database so it is reviewable. Source material is the migrated D7 text: node 3 "Who We Are" for
`/about`, node 29 "Ministries" for `/ministries`.

**Two things found that mattered more than the copy:**

1. **Four "Donate" buttons pointed at `https://easebuzz.in/demo/`** — a third-party payment
   demo site — across `/about`, `/ministries`, `/give`, `/news` and `/news-story`. Nobody's
   giving should ever have gone there. All swept.
2. **The `/give` page carried fabricated donation tiers** — $19/$29/$49 per month with
   "Supports one family", "Donor recognition", "Event invitations". Deleted outright rather than
   repointed; that is not how a country church receives giving.

Also repointed `/impact` links (the page is deleted), `/canvas_page/2` (an entity path, not a
URL), and `/who-we-are/our-leadership`.

**⚠ Outstanding — MCC's real online giving provider is unknown**, so the Give buttons currently
point at `/contact` with the label "Ask about giving". Change `GIVING_URL` at the top of
`scripts/ia-landing-links.php` once the real arrangement is known.

Verified clean across all 11 live pages: no "Community Impact Network", no "CareSphere" in
visible copy, no easebuzz, no invented statistics or testimonials. (The string `caresphere`
still appears in page source as `data-component-id="caresphere_theme:…"` — that is the base
theme's name, not content.)

**Still demo copy, lower priority:** `/news` and `/news-story` (nothing links to them but a
footer entry) and `/im-new`, which still carries the `/get-involved` copy it was cloned from and
needs D7 node 2's text written into it properly.

### Sermons section (new)

Built for Alex Jones's archive. **The content model only — no mp3s are loaded yet.**

- **`audio` media type** — created with core's `audio_file` source, which had no media type on
  this site (only document, image, remote_video, svg_image, video). Accepts
  `mp3 m4a wav ogg aac`, max 64 MB. The eight mp3s in the D7 `file_managed` table average about
  10 MB, so the real archive will be well within that.
- **`sermon` content type** — `field_sermon_date`, `field_sermon_speaker` (→ `bio`),
  `field_sermon_scripture`, `field_sermon_series` (→ new `mcc_sermon_series` vocabulary),
  `field_sermon_audio` (→ `audio` media), and `field_content` for notes, reusing the shared
  `field_content` storage like every other bundle.
- **`mcc_sermons` view**, block display only, newest first, 20 per page, with an empty-state
  message so the page reads properly before any sermons exist.
- **`/sermons`** is Canvas page 12, purpose-built (heading + listing) rather than cloned from a
  demo page. The empty `page` node 1601 that was squatting on the slug is unpublished and its
  alias removed.
- Slug pattern: `/sermons/[node:field_sermon_date:date:custom:Y-m-d]-[node:title]`, verified as
  `/sermons/2026-07-19-vine-and-branches`.

**Outstanding:** bulk-loading the back catalogue. `media_library_bulk_upload` is already enabled
and handles the file side; the metadata pass (date, speaker, scripture, series) depends on how
the files are named, which we haven't seen.

**A re-import will not touch any of this** — sermons are not produced by any migration.

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
