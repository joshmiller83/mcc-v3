# Mechanicsburg Christian Church — Website Rebuild

This repo is the successor to [mechanicsburgchristian.com](https://mechanicsburgchristian.com/). It is built on a fresh install of **Drupal CMS**, designed to be simple to maintain and easy to keep updated for years to come.

Mechanicsburg Christian Church is a small country church. We read the Bible, we believe Jesus made a way for all sinners to repent and join him in his kingdom, and we love people unconditionally. This website exists to serve that mission — nothing more complicated than that.

## About Drupal CMS
Drupal CMS is an open source product that enables site builders to easily create new Drupal sites and extend them with smart defaults, all using their browser.

## Stack

- **CMS:** Drupal CMS (latest), fresh install — not a migration of the old site's codebase
- **Local dev:** [DDEV](https://ddev.com/)
- **Hosting:** [Pantheon](https://pantheon.io/)
- **Deploys:** push to GitHub → Pantheon build process picks it up (integration TBD)

## Getting started

Development primarily happens in **GitHub Codespaces** (see [AGENTS.md](file:///workspaces/mcc-v3/AGENTS.md)). Open this repo in a Codespace — `.devcontainer/devcontainer.json` installs Docker-in-Docker and DDEV automatically via DDEV's official [`install-ddev`](https://github.com/ddev/ddev/tree/main/containers/devcontainers/install-ddev) devcontainer feature.

Once the codespace is up:

```bash
ddev start
ddev composer install
ddev drush si   # or drush updb / cim, depending on where the site is
```

Site should then be reachable at the URL `ddev` prints out (`ddev launch` also works).

### Run Locally (Outside Codespaces)
If you want to use [DDEV](https://ddev.com) to run Drupal CMS locally on your host machine, follow these instructions:

1. Install DDEV following the [documentation](https://ddev.com/get-started/)
2. Open the command line and `cd` to the root directory of this project
3. Run `ddev launch`

Drupal CMS has the same system requirements as Drupal core, so you can use your preferred setup to run it locally. [See the Drupal User Guide for more information](https://www.drupal.org/docs/user_guide/en/installation-chapter.html) on how to set up Drupal.

### Installation options

The Drupal CMS installer offers a list of features preconfigured with smart defaults. You will be able to customize whatever you choose, and add additional features, once you are logged in.

After the installer is complete, you will land on the dashboard.

**Codespaces prebuilds:** not yet enabled. This can only be configured through the repo's Settings → Codespaces UI (not as code), and it consumes Codespaces storage quota, so it's an opt-in decision rather than something set up automatically.

## Environments

Terminus gives access to Pantheon's `dev`, `test`, and `live` environments via `ddev terminus`. See [AGENTS.md](file:///workspaces/mcc-v3/AGENTS.md) for rules on when/how those commands should be used.

## The calendar

`/calendar` is the month grid and `/calendar/print` is the one-page bulletin insert. Both are served by `mcc_core` and rendered with Single Directory Components in `mcc_theme`.

Everything that varies is data, not code:

- **Colour and shape** live on the *Mission category* taxonomy — `field_category_color` (a [Color Field](https://www.drupal.org/project/color_field) hex value) and `field_marker_shape` (circle, square, triangle, diamond, hexagon, ring). Add a category in the admin UI and it shows up on the calendar, in the legend and in print with no code change. The colour is emitted as a `--evt` custom property on each element and every tint is derived from it with `color-mix()`.
- **Layout choices** live in config at **Administration → Configuration → Content authoring → Calendar** (`mcc_core.calendar.settings`): the eyebrow text, day density, whether to show the legend, and the print sheet's tagline, footers, adjacent-day shading, and busy-day style.

Two behaviours worth knowing about:

- **Nothing collapses.** There is no "+3 more" — a day shows every event it has, and the print sheet shrinks its type (server-side estimate, then a client-side fit loop) until the whole month fits on 8.5×11 without paginating or clipping.
- **Multi-day events are derived, not declared.** An editor just enters the event on each day it happens. `CalendarMonth` collects the days a node claims, splits them into runs of consecutive dates, and renders any run of two or more as a band spanning those columns. A single occurrence long enough to cover several days produces the same band.

### "This week at MCC" on the front page

The front page panel is the calendar, not a summary of it. `CalendarMonth::week()` builds one Sunday-to-Saturday row through the same collect / segment / lane-pack steps `build()` uses, and the `mcc-this-week` component wraps `mcc-calendar-week` unchanged — so a recoloured category, a new marker shape or a differently-derived multi-day band lands on the front page and `/calendar` together. Today's column carries the same tint and "Today" pill it does on the calendar, and below 992px the panel swaps to the same agenda list.

It is exposed as the **`mcc_this_week` block plugin**, which Drupal Canvas offers as a component. Placing it, moving it, or taking it off a page is an editor's job in the Canvas UI, so the block itself has no settings — a placement isn't a code change.

Note that *placement* is content, not config: the front page is a `canvas_page` entity, so `config:export` won't carry the panel to another environment. The block and component deploy with the code; dropping it onto that environment's front page is a one-time edit in Canvas.

### Comparing against the design reference

`scripts/calendar-compare.mjs` renders the design handoff (`calendar_design.zip`) and the live Drupal pages side by side in headless Chromium, and asserts that the print sheet is exactly one Letter page with nothing clipped.

```bash
node scripts/calendar-compare.mjs             # defaults to a 5-week and a 6-week month
node scripts/calendar-compare.mjs 2026-11     # a specific month
```

It writes screenshots plus `compare.html` and `report.md` to the gitignored `.calendar-compare/`, and exits non-zero if the print assertions fail — so it doubles as a regression check after any calendar change.

### Reviewing a landing page for Claude Design handoff

`scripts/im-new-review.mjs` captures any route in headless Chromium (desktop + mobile), auto-enables local Twig debug comments, runs a readability/visual-quality audit, and writes a handoff HTML file with Canvas component metadata plus Twig template hints. It defaults to `/get-involved`; pass `--path` for anything else.

```bash
node scripts/im-new-review.mjs
node scripts/im-new-review.mjs --path /im-new
node scripts/im-new-review.mjs --base http://127.0.0.1 --path /about --out .im-new-review
```

Two caveats worth knowing, both fixed in the probe but easy to reintroduce:

- **Colours are normalised through a canvas, not parsed with a regex.** The design tokens are `color-mix(in oklch, …)`, which `getComputedStyle` returns verbatim — matching only `rgb()` made every brand background unreadable, so the walk-up fell through to white and scored light-on-dark text against white.
- **Line length is only measured on text that actually wraps,** and visually-hidden skip links are excluded from tap targets. Otherwise nav labels, eyebrows and a correct 1×1 skip link all report as defects.

Output lands in gitignored `.im-new-review/`, including:

- `claude-design-handoff-im-new.html` (pass this to Claude Design)
- `drupal-im-new-desktop.png` and `drupal-im-new-mobile.png`
- `im-new-source.html` (raw source with Twig debug comments when available)
- `component-map.json` and `audit.json`
- `report.md`

## Our Leadership and bio pages

`/who-we-are/our-leadership` lists the people who lead the church, and each of them has a page of their own. Both are driven by the *Leadership Structure* taxonomy rather than by anything hardcoded.

The listing is **two views, not one**. `mcc_leadership_groups` lists the terms; `mcc_leadership` lists the people in *one* group, taking the term as a contextual filter, and is embedded once per term. That has three consequences worth knowing:

- **Reordering the vocabulary reorders the page.** Sections come out in term order, from Administration → Structure → Taxonomy → Leadership Structure.
- **A group with nobody in it is skipped.** No empty heading, no placeholder.
- **Somebody in two groups is listed under both**, with an "Also serves as Trustee" line on the second card, and a note at the foot of the page saying how many people that applies to. No de-duplication rule is needed because a person is never looked up twice — each section asks its own question.

What a group *says* lives on its term:

- `description` — the role explainer ("What deacons do"). This is the paragraph that carries a bio page: only three of twenty people have written a biography, so for everyone else it is the substance of the page. Seed or reset it with `scripts/mcc_setup_leadership_groups.php`.
- `field_role_singular` — "Elder" for "Elders", used as the eyebrow above a person's name.
- `field_feature_group` — draw this group as one wide card above the sections instead of a grid. It is on for Senior Minister, because a group of one rendered as a grid of one looks like a mistake.

On the person, `field_role` holds the role that used to be jammed into the node title, and `field_topics` holds the "Reach out about" chips. Portraits come from `field_bio_pic` through the 3:4 focal-point styles; anyone without one gets their initials as a monogram, not a silhouette — a generic person icon reads as a broken image on a page of real faces.

### Contact details are not published

Fourteen of these people have a phone number on file and six have an email, and none of them agreed to publish it — the values are there because somebody typed them into the legacy site years ago. So a bio page shows a short message form (the `bio_contact` webform, one form reused across every bio with the person passed as hidden data) that goes to the church office, plus the office number as a visible fallback.

`field_email` and `field_phone_number` are left out of every view display, and the template builds those lines itself only when **`field_publish_contact`** is on for that person. The switch is off by default and is meant to be turned on one person at a time, after asking them. Keeping the gate in PHP rather than in the display means turning a formatter back on in the admin UI cannot publish anything by accident.

### Duplicate people are merged, not de-duplicated at render time

Two people were entered twice on the legacy site — once per board they serve on — instead of once with two roles. Gary Allen had a deacon record and a trustee record; Jon Culbertson had the same. `BioDuplicateMerger` in `mcc_migration` folds each pair into the older record: it gains the other's leadership group, is filled in from whatever the retired record had that it lacked, takes over any references and redirects aimed at it, and the duplicate is **unpublished rather than deleted** so the merge is reversible.

It is not a migration process plugin because it cannot be. The two rows are separate source nodes and a process plugin only ever sees one row, so there is no point at which the trustee row can add its term to the deacon node. Skipping the duplicate row instead would drop the second term — the one piece of information it actually carries. So the merge runs on `POST_IMPORT` for `mcc_bio`, the same way the focal point conversion runs after `mcc_files`, and a re-import re-merges without anyone remembering a follow-up step.

Pairs are listed explicitly in the class rather than found by matching titles — a congregation can have two people with the same name, and merging two real people is not worth risking on a string comparison. Each pair records the person's name, and the merge refuses (with a warning) if either record no longer starts with it.

### One-off content scripts

Four scripts under `scripts/` fix things the D7 migration brings over as-is. All are safe to re-run. **Run them in this order** after a re-import:

```bash
ddev drush php:script scripts/mcc_setup_leadership_groups.php   # term explainers, singular labels, featured flag
ddev drush php:script scripts/mcc_split_bio_name_role.php       # "Alan Martin Finance " -> title + field_role
ddev drush php:script scripts/mcc_clean_bio_bodies.php          # Word paste residue out of the biographies
ddev drush php:script scripts/mcc_merge_duplicate_bios.php      # fold the duplicate records together
```

The merge goes last even though the migration already ran it once. Jon Culbertson's "Finance" role lives on the record being retired, and it only exists as a *field* after the name/role split has run — so the merge has to pass over it again to carry it onto the surviving record.

## Ministries

`/ministries` lists every ministry the church runs, grouped, and each one has a page of its own. Like the leadership listing, nothing about the grouping is hardcoded — it comes from the *Ministry Groups* (`mcc_ministry_groups`) vocabulary.

The listing is **two views, not one**, the same shape as Our Leadership. `mcc_ministry_groups` lists the terms; `mcc_ministries` lists the ministries in *one* group, taking the term as a contextual filter, and is embedded once per term. So:

- **Reordering the vocabulary reorders the page**, and adding a term adds a section. Manage them at Administration → Structure → Taxonomy → Ministry Groups.
- **A group with nothing published in it is skipped** — no empty heading over a hole.
- **Ordering inside a group is `field_weight`, then title.** Alphabetical alone is wrong here: it put "Building & Grounds" at the top of the page.

What a group *says* lives on its term: `name` is the heading, `description` is the blurb beside it, `field_group_eyebrow` is the small line above it, and the term's weight is its position. Seed or reset all of it with `scripts/ministry-groups.php`.

The listing covers **two bundles**, `ministry` and `missions`. A visitor reading `/ministries/emergency-response` has no idea it is a different content type from `/ministries/womens`, so the split should not be visible — five ministries that arrived from D7 as `page` nodes were converted to `ministry` for exactly that reason (`scripts/ministries-content.php`). The two `missions` nodes are supported partner organisations; they render the same card in a `partner` variant and sit in the same grid as the Missions ministry, which would otherwise be alone in a three-column row.

### The fields a ministry page is built from

Nothing on either page is parsed out of prose. `field_summary` is the card dek and the detail page's lede; `field_display_title` overrides an awkward title ("C.A.R.E. (Christians Are Reaching Everyone) MINISTRY" → "C.A.R.E. Ministry") and falls back to the title when empty; `field_subtitle` carries age ranges and acronym expansions; `field_schedule` answers "when could I show up"; `field_time_commitment` answers "what would this ask of me" and appears only on the detail page, because it addresses a different reader.

`field_verse_text` and `field_verse_reference` are **both unlimited and paired by position** — the reference at position 2 belongs to the verse at position 2. Women's Ministry needs two verses with two citations, which is why one field could not do it. They sit next to each other in the edit form for that reason; keep them in step.

A ministry with no summary, no schedule, no leader or no events is normal, and the templates omit rather than invent. The one exception is the summary: an empty dek makes the grid look broken, so a ministry without one gets a visible "a description is on the way" note instead of a blank line.

**Upcoming dates** come from the reverse reference on `calendar_event.field_related_ministry`, through `EventContext::upcomingForMinistry()` — the same service the calendar uses, so the two can't disagree about what "upcoming" means. As of the current content, no ministry has any *future* events, so that row is absent everywhere; the lookup works, the calendar just hasn't been filled forward.

### Icons

Icons everywhere on the site come from **core's Icon API** over the Lucide pack (`drupal/lucide` + the `lucide-static` library, both installed by Composer). Editors pick one from a searchable list of ~1,900 through `ui_icons_field`'s picker — on a ministry (`field_icon`) and on a Mission Category (`field_icon`, shown on event pages).

In templates it is `{{ icon('lucide', 'church', { size: 22 }) }}`. There is no second icon mechanism: the category icons used to be per-category SVG *media entities* an editor had to draw and upload, tinted through CSS `mask-image`, and the header, print button and photo placeholder each carried their own inline `<svg>`. All of them now go through the same call. Adding an icon to a new content type is a field, not a code change.

### One-off content scripts

All idempotent, all safe to re-run — which matters, because a migration re-import undoes them. Run in this order:

```bash
ddev drush php:script scripts/ministry-groups.php      # the three group terms, their order, blurbs and eyebrows
ddev drush php:script scripts/ministries-content.php   # bundle conversion + every field value on all thirteen
ddev drush php:script scripts/ministries-cleanup.php   # Word residue, the time-commitment lift, two data fixes
ddev drush php:script scripts/ministries-page.php      # the /ministries Canvas page tree
```

`node scripts/ministries-review.mjs --login "$(ddev drush uli --uri=http://127.0.0.1)"` screenshots both pages at three widths, anonymously and logged in, and fails on horizontal overflow or a collapsed icon tile. The logged-in pass is not optional — see the `.contextual-region` trap in [AGENTS.md](file:///workspaces/mcc-v3/AGENTS.md).

## Ground rules

- Keep it clear and straightforward. This is a small church site, not an enterprise platform — prefer boring, well-supported Drupal patterns over clever ones.
- Favor core and well-maintained contrib modules over custom code. Custom code should be the exception, and only when there's no reasonable alternative. Always prefix custom modules, custom themes, and custom components with `mcc_` (or `mcc-` for component/folder names) to maintain namespaces.
- Keep the site upgradable. Don't fight Drupal CMS's defaults without a good reason.
- Document any non-obvious decision in the commit message or a code comment — future maintainers (human or AI) won't have this conversation's context.

See [AGENTS.md](file:///workspaces/mcc-v3/AGENTS.md) for rules specific to AI coding assistants working in this repo.

## Documentation

* [Drupal CMS User Guide](https://project.pages.drupalcode.org/drupal_cms/)
* Learn more about managing a Drupal-based application in the [Drupal User Guide](https://www.drupal.org/docs/user_guide/en/index.html).

## Contributing & Support

[Report issues in the queue](https://drupal.org/node/add/project-issue/drupal_cms), providing as much detail as you can. You can also join the #drupal-cms-support channel in the [Drupal Slack community](https://www.drupal.org/slack).

Drupal CMS is developed in [a separate repository on Drupal.org](https://www.drupal.org/project/drupal_cms). See [CONTRIBUTING.md](CONTRIBUTING.md) for more information.

## License

Drupal CMS and all derivative works are licensed under the [GNU General Public License, version 2 or later](http://www.gnu.org/licenses/old-licenses/gpl-2.0.html).

Learn about the [Drupal trademark and logo policy here](https://www.drupal.com/trademark).
