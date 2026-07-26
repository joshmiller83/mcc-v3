# Redesign the Calendar Event detail page

## Context

`calendar_event` nodes currently render with zero custom design — there's no
`node--calendar-event.html.twig` anywhere in `mcc_theme` or its base theme
`caresphere_theme`, so Drupal falls back to dumping the `default` view mode's
fields in a plain vertical list (confirmed: `core.entity_view_display.node
.calendar_event.default.yml` uses stock formatters — `entity_reference_label`,
`media_thumbnail`, `smartdate_default` — with no template to arrange them).
The registration webform field is even configured `hidden: true`, so it never
renders at all today.

Two real content-model gaps surfaced while reviewing the fields:

1. **No description field.** `mcc_calendar_event.yml` maps `body: body`, but
   `calendar_event` has no `body`-equivalent field at all — so all 892
   migrated events lost their D7 body text silently. (The same dead mapping
   exists in `mcc_bio.yml`, `mcc_ministry.yml`, `mcc_missions.yml` — out of
   scope here, worth a follow-up, not touched in this task.)
2. **Category color is hardcoded by name.** `MccCalendarController::
   CATEGORY_ACCENTS` is a PHP constant that string-matches lowercased term
   *labels* ("worship", "serve", …) to a fixed accent slug, which the CSS then
   maps to a color. Renaming a term or adding a new Mission Category term
   requires a code deploy to get a color — exactly the non-DRY pattern the
   user flagged. Same idea applies to icons: there's currently no icon
   concept at all for categories.

This plan fixes both, then builds a real Event detail page — hero, key facts,
description, speaker/ministry context, attachments, registration CTA, and a
"more this day / this week" sidebar — using a `node--calendar-event.html.twig`
template, view-mode-driven teasers for referenced entities, and a small set of
reusable SDCs, all styled off color/icon values that live on the taxonomy term.

## Decisions from investigation

- **Icon field = Media reference, not `ui_icons`.** `caresphere_theme`'s
  `badge`/`icon` SDCs (which we'll reuse for the category pill) only render
  icons via an uploaded SVG **Media** image (`icon.twig` reads `media.src`;
  the string-based `icon` prop is explicitly marked "Legacy — do not use").
  Every other image-ish field in this project (`field_featured_image`,
  `field_attachments`) is already a Media reference, and `svg_image` is
  already enabled. So `field_icon` on the taxonomy term will be an
  `entity_reference` to `media` (bundle `image`, same as `field_featured_image`),
  not the unused `drupal/ui_icons` package — reuses existing, working
  components and the same familiar media-library picker editors already use
  for photos, instead of introducing 3 new modules and a hand-vendored icon
  pack for a icon-ID-autocomplete widget that doesn't fit this theme's actual
  icon-rendering convention.
- **Color field = fixed `list_string` enum**, not a free color picker. Values
  are the exact 6 swatches already hardcoded in `mcc-calendar-month.css`
  today (`green-700`, `terracotta-500`, `walnut-600`, `brick-500` (oklch),
  `green-500`, `oklch(64% 0.10 72)`), just formalized under stable semantic
  keys (`forest`, `terracotta`, `walnut`, `brick`, `sage`, `sand`) so the
  visual output is unchanged but now data-driven. This is a bounded, on-brand
  palette rather than an arbitrary hex picker — appropriate for a small site
  a non-specialist maintains, and it directly reuses the design tokens in
  `css/tokens/colors.css` rather than inventing new colors.
- **`field_content`, not a new `body` field.** `field_content` (`text_long`,
  shared field storage, already used by `page`) is unattached-but-persistent
  and a perfect DRY reuse for the event description — same field storage, same
  `content_format` text format, one new `field.field.node.calendar_event
  .field_content.yml` instance. No new field storage needed.
- **Context panel via `hook_preprocess_node()`**, not a new controller/route.
  Node canonical pages already go through core's normal render pipeline;
  `mcc_theme.theme` has no `hook_preprocess_node()` yet (confirmed — this'll
  be the first). This is the idiomatic place to compute extra template
  variables (sibling events) without touching routing.
- **Config authored directly in `config/sync/`, then `drush config:import`**,
  matching how this repo already manages config (per `AGENTS.md`), rather
  than clicking through the Field UI.

## Field/content model changes

1. `field.field.node.calendar_event.field_content.yml` — attach existing
   `field_content` storage to `calendar_event`, label "Event Description",
   `allowed_formats: [content_format]` (copy `field.field.node.page
   .field_content.yml` pattern).
2. `field.storage.taxonomy_term.field_accent_color.yml` (`list_string`,
   allowed values `forest`/`terracotta`/`walnut`/`brick`/`sage`/`sand`) +
   `field.field.taxonomy_term.mcc_mission_category.field_accent_color.yml`.
3. `field.storage.taxonomy_term.field_icon.yml` (`entity_reference` →
   `media`, `target_bundles: {image: image}`, cardinality 1) +
   `field.field.taxonomy_term.mcc_mission_category.field_icon.yml`.
4. Add both fields to a new `core.entity_form_display.taxonomy_term
   .mcc_mission_category.default.yml` (or extend if one exists) so editors
   can actually set them.
5. Update `core.entity_view_display.node.calendar_event.default.yml`: add
   `field_content` (basic_string/text_default formatter), **un-hide**
   `field_connect_webform` (currently `hidden: true` — the registration CTA
   can't render otherwise).
6. Fix `mcc_calendar_event.yml`: `body: body` → `body: field_content`.
7. Add `core.entity_view_display.node.bio.teaser.yml` (photo + name +
   `field_ministry_structure` badge) and `core.entity_view_display.node
   .ministry.teaser.yml` (name + linked) — both reuse the existing global
   `teaser` view mode (no new view-mode entities), used for the speaker card
   and related-ministry callout.
8. Populate the 6 existing Mission Category terms' new fields (Worship→
   forest, Serve→terracotta, Fellowship→walnut, Youth→brick, Equip→sage,
   Lead→sand) — preserves today's exact colors. Upload ~6 small hand-authored
   line-icon SVGs (music note/heart-hands/users/book/compass/globe — simple
   original stroke shapes, not copied from a licensed set) as Media image
   entities and attach one per term.

## Code changes

- `MccCalendarController::collectOccurrences()` (`web/modules/custom/mcc_core
  /src/Controller/MccCalendarController.php`): delete the `CATEGORY_ACCENTS`
  constant; read `$term->get('field_accent_color')->value ?: 'default'`
  directly instead of name-matching. Legend label still comes from
  `$term->label()`. Behavior-preserving for the 6 existing terms; now also
  correctly colors any *future* term without a code change.
- `mcc-calendar-month.css`: rename the 6 `[data-accent="worship"]` etc.
  selectors to the 6 new color-key selectors (`forest`/`terracotta`/`walnut`
  /`brick`/`sage`/`sand`) — same var values, same visual result.
- `mcc_theme.theme`: add `mcc_theme_preprocess_node()`. For
  `calendar_event` bundle, compute and set template variables:
  - `mission_category` — term label, accent key, rendered icon (via the
    `icon.twig` include pattern, `media` prop built from the term's
    `field_icon`).
  - `speaker_cards` — `field_bio_reference` targets rendered via
    `getViewBuilder('node')->view($bio, 'teaser')`.
  - `ministry_card` — first `field_related_ministry` target rendered via
    `teaser` view mode (field is multi-value; render all if more than one).
  - `attachments` — `field_attachments` targets resolved to
    `{label, url, filesize}` (documents need a name+download link, not an
    image thumbnail — `media_thumbnail` is the wrong formatter for PDFs).
  - `same_day_events` / `this_week_events` — sibling `calendar_event` nodes
    whose `field_event_date` occurrences overlap this event's own day / the
    Sun–Sat week containing this event's date (anchored to the *viewed*
    event's date, not "today", so past events still show sensible context),
    excluding the current node, capped (~5 each), shaped like
    `MccCalendarController`'s chip arrays (`title`, `url`, `time_label`,
    `accent`) for direct reuse with `mcc-event-card`. This intentionally
    duplicates a small, simplified slice of `collectOccurrences()`'s
    query/bucketing logic rather than extracting a shared service — the
    query is ~10 lines and single-purpose; not worth an abstraction for two
    call sites in a small codebase.

## Templates / components

- New `web/themes/custom/mcc_theme/templates/content/node--calendar-event
  .html.twig` (new `templates/content/` dir, matching the existing
  `templates/layout/` convention). Sections, in order:
  1. Hero — `field_featured_image` if present, else the existing
     `mcc-photo-placeholder` component (already built for exactly this
     fallback); title; category badge (reuse `@caresphere_theme` `badge`
     component: label + icon + link to the term's canonical
     `/taxonomy/term/{tid}` page — zero new routes).
  2. Key facts row — date/time (`content.field_event_date`, already
     formatted by `smartdate_default`), related ministry link(s).
  3. Description — `content.field_content`.
  4. Speaker/host — `speaker_cards` (bio teasers), hidden if empty.
  5. Attachments — `attachments` list (name + download icon), hidden if
     empty.
  6. Registration — `content.field_connect_webform` in a highlighted CTA
     card, hidden if empty.
  7. Sidebar (`<aside>`, reusing the two-column pattern already in
     `page.html.twig`'s `sidebar_first` markup for consistency) — "Also
     happening this day" (`same_day_events`) and "This week" (
     `this_week_events`), each rendered as `mcc-event-card` items; whole
     sections hidden if empty.
- Extend `mcc-event-card.component.yml`/`.twig`/`.css` with optional
  `accent` (color key) and `icon` (render array) props, applied the same
  `data-accent` + `--evt` custom-property convention as the calendar month
  view — additive/backward compatible, existing callers unaffected.
- New CSS file `mcc-event-detail.css` (or extend `global-overrides.css` if
  more consistent with existing conventions — decide while building,
  following whichever pattern the theme already leans on) for hero/key-facts
  /sidebar layout, using existing design tokens throughout (no new raw
  colors).

## Test content

Create via `drush php:eval` (Entity API, matching this session's established
pattern), clearly titled `TEST: …`, left published for the user to review in
a browser:

1. **Kitchen sink** — every field filled: featured image, multi-paragraph
   formatted description, category (icon+color), related ministry, 2 bio
   references, 2 attachments, registration webform, normal single-day timed
   occurrence.
2. **All-day** — Smart Date all-day, otherwise minimal.
3. **Multi-day** — spans 3 days.
4. **Bare minimum** — no image (tests placeholder fallback), no speaker, no
   ministry, no attachments, no webform — verifies every optional section
   hides cleanly.
5. **Same-day companion** — same date as #1, different time (exercises
   "also happening this day").
6. **Same-week filler** — same Sun–Sat week as #1, different day (exercises
   "this week").

## Verification

1. `ddev drush config:import -y`, `ddev drush cache:rebuild`.
2. `ddev drush migrate:import mcc_calendar_event --update -y` to backfill
   `field_content` from the (now-correctly-mapped) source for all existing
   events — real test that the earlier `vid` fix holds up under `--update`.
3. Set the 6 taxonomy terms' color/icon values; confirm via
   `ddev drush php:eval` that `/calendar` legend/chip colors are visually
   unchanged (spot-check in browser).
4. Create the 6 test events.
5. Use the `run` skill (or `claude-in-chrome`) to load the kitchen-sink and
   bare-minimum event pages plus `/calendar` in a real browser; screenshot
   and visually confirm layout, empty-state hiding, and responsive behavior;
   iterate on CSS as needed.
6. `ddev drush watchdog:show --severity=Error` after page loads — confirm no
   PHP errors/warnings.
