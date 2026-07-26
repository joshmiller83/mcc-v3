# Event detail page redesign — status

**Last updated:** 2026-07-26 · paused mid-task, nothing committed yet.
Full approved plan: `/home/vscode/.claude/plans/ticklish-wobbling-moth.md`
(outside the repo — copy it in if it needs to survive the Codespace).

---

## Where this left off

The content model, the shared service, and the event detail page are **built and
working**. What remains is mostly verification: purpose-built test content and a
real browser pass.

Verified working so far:

- `/calendar` still renders correctly after the refactor (HTTP 200, all six
  accent colours present in the markup).
- Event pages render with the new template — e.g. `/worship-team-rehearsal-2`
  shows the hero, date chip, category badge with its icon, "Event details",
  "Also happening that day" (5 rows) and "Rest of that week" (6 rows).
- Empty states hide cleanly — `/december-youth-conference` sits in a week with
  genuinely no other events, and both context panels correctly disappear rather
  than rendering empty headings.
- No new PHP errors in watchdog (the 3 errors still listed are older: one
  transient `TypeError` from mid-refactor before a cache rebuild, and two
  duplicate-`vid` errors from yesterday's migration work).

---

## Done

### Content model (all exported to `config/sync`, `config:status` is clean)

- **`field_content` attached to `calendar_event`** as "Event Description".
  `calendar_event` previously had *no* body-equivalent field at all, so the D7
  body text of all 892 migrated events had nowhere to land. Reuses the existing
  shared `field_content` storage (already used by `page`) rather than adding a
  second long-text field. Visible in both the form and view displays.
- **`field_accent_color`** on `mcc_mission_category` — a `list_string` enum of
  six brand values (`forest`, `terracotta`, `walnut`, `brick`, `sage`, `sand`).
- **`field_icon`** on `mcc_mission_category` — entity reference to the existing
  `svg_image` media type (already accepts `.svg`; no new media type, and editors
  get the media library they already use).
- **`node.bio.teaser` view display** — photo + ministry role, used for the
  speaker cards. Email/phone deliberately excluded from a public page.
- **`calendar_event` view display** reworked: `field_connect_webform` was
  `hidden: true` and so could *never* render — now visible. Fields assembled in
  preprocess (speakers, ministries, attachments, category) are hidden here so
  they don't render twice.
- All six Mission Category terms seeded with their colour + a generated icon.

### Code

- **`web/modules/custom/mcc_core/src/EventContext.php`** (new service,
  `mcc_core.event_context`) — the single source of truth for: what a Mission
  Category looks like (`category()`), which events fall in a date window
  (`findEventsInRange()`), and how to describe one occurrence in words
  (`describeOccurrence()`). Both the calendar and the event page use it, so the
  two can't disagree.
- **`MccCalendarController`** — deleted the hardcoded `CATEGORY_ACCENTS`
  constant that string-matched lowercased term *names* to colours (renaming a
  term silently dropped its colour; adding one needed a deploy). Colour now
  comes off the term. Legend orders by the vocabulary's own term weights.
  Added `taxonomy_term_list:mcc_mission_category` to the page cache tags.
  Also fixed dead code: it read `$node->get('body')`, a field
  `calendar_event` does not have, so calendar day-detail descriptions were
  always blank — now reads `field_content`.
- **`mcc_theme.theme`** — added `hook_preprocess_node()` (the theme's first)
  plus helpers building the event context: occurrences (anchored to the *next
  upcoming* one, falling back to the most recent past one), speakers,
  ministries, attachments, and same-day / same-week neighbours.

### Templates & styles

- `templates/content/node--calendar-event--full.html.twig` — hero, category
  badge, description, speakers, attachments, registration, and a sticky aside.
  Every optional section is individually guarded.
- `components/mcc-event-list/` — new SDC, used for both sidebar panels.
- `css/tokens/accents.css` — **the** accent palette, `[data-accent]` → `--evt`.
  Extracted out of `mcc-calendar-month.css` so calendar, event page and event
  list all read the same six values.
- `css/mcc-event-detail.css` — event page layout.

Category icons render via CSS `mask-image` rather than inline SVG: one file
tints to any category colour, and editor-uploaded SVG markup never reaches the
page.

---

## What's next

1. **Create the test events** (the plan's six cases) — kitchen sink with every
   field populated, all-day, multi-day, bare minimum (no image / speaker /
   ministry / attachment / webform), a same-day companion, and a same-week
   filler. Title them `TEST: …` so they're easy to find and delete.
2. **Browser pass** — use the `run` skill or `claude-in-chrome` on the
   kitchen-sink and bare-minimum events plus `/calendar`; screenshot, check
   responsive behaviour, iterate on CSS. Nothing has been looked at in a real
   browser yet — only markup has been grepped.
3. **Backfill event descriptions:** `mcc_calendar_event.yml` now maps
   `body: field_content` (was `body: body`, a dead mapping). Run
   `ddev drush migrate:import mcc_calendar_event --update -y` to pull the D7
   body text into all 892 events. This also re-tests yesterday's `vid` fix
   under `--update`.
4. Optional follow-up, **not** in scope here: `mcc_bio.yml`,
   `mcc_ministry.yml` and `mcc_missions.yml` have the same dead `body: body`
   mapping and are silently dropping their D7 body text too.

---

## Reproducing on another environment

`ddev drush config:import -y` covers the fields and displays. Taxonomy term
values and icon media are **content**, not config, so run:

```bash
ddev drush php:script ../scripts/mcc_setup_category_styles.php
```

(idempotent — safe to re-run; it rewrites the icon SVGs and re-points the terms).

---

## Uncommitted

Everything above is uncommitted. To save it:

```bash
git add -A && git commit -m "feat(events): data-driven category styling and a real event detail page"
```

Also still uncommitted from yesterday's session: the migration `vid` fix across
five `mcc_migration` YAMLs, and the `AGENTS.md` secrets/tokens section.
