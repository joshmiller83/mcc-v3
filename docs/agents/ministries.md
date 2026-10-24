# Ministries

_Part of the agent guidance indexed in [AGENTS.md](../../AGENTS.md). Read it before working on this area._

`/ministries` and every ministry detail page are built from the *Ministry Groups*
(`mcc_ministry_groups`) vocabulary and fields on the `ministry` and `missions` bundles — see the
"Ministries" section of `README.md` for the shape of it. When working on them:

- **Never hardcode a group's name, order, blurb or eyebrow.** Section order is the vocabulary's
  term order, the blurb is the term `description`, the eyebrow is `field_group_eyebrow`. Adding a
  fourth group or reordering the page must be possible from the admin UI alone — the same rule as
  `mcc_leadership_structure`. The band this replaced was three hand-typed Canvas cards naming the
  same three groups, which is exactly the duplication to avoid re-introducing.
- **Keep the listing two views.** `mcc_ministry_groups` lists terms, `mcc_ministries` lists one
  group's ministries from a contextual filter and is embedded per term by
  `_mcc_theme_ministry_group_sections()`. It is a *block* display embedded in the Canvas page, not
  a page display — no new view owns a route.
- **`mcc_ministries` covers both `ministry` and `missions`.** Don't "fix" the bundle filter down to
  one. The split is invisible to a visitor and was the reason the old listing showed six of eleven
  ministries.
- **Nothing is parsed out of `field_content` at render time.** Everything the card and the page
  show is its own field. The one lift that *was* done — the "Time Commitment:" paragraph into
  `field_time_commitment` — happened once, in `scripts/ministries-cleanup.php`, and the marker is
  gone from the bodies now.
- **`field_verse_text` and `field_verse_reference` are unlimited and pair by delta.** The reference
  at delta N belongs to the verse at delta N. They are adjacent in the form display on purpose;
  anything that writes one must write the other, which is why
  `scripts/ministries-content.php` always sets both together.
- **Sorting inside a group is `field_weight` ASC then title.** `field_weight` defaults to 0 so a
  new ministry lands with everything else rather than NULL-first; without that default the
  LEFT JOIN puts an unweighted ministry at the top of its group, which is the original
  "Building & Grounds leads the page" bug in a new shape.
- **The detail page is a bundle template, not a Canvas content template.** The handoff asked for
  `canvas.content_template.node.ministry.full`; that works for `page` because every one of
  mcc-page-body's props maps to a single field, and it does not work here — the group link, the
  leader's portrait and role, the next occurrences and the sibling rail are all derived, and Canvas
  prop expressions only address fields on the node. `node--ministry--full.html.twig` over
  `_mcc_theme_ministry_context()` is the same shape `node--bio--full` and
  `node--calendar-event--full` already use.
- **The title band is one shared component.** `mcc_theme:mcc-title-band` is used by the Canvas
  landing page *and* by the detail template, so the two cannot drift. Its slots are passed as
  variables to `{% include %}`, not as `{% block %}`s to an `{% embed %}` — an embed's blocks can
  only override blocks the embedded template declares, and the component prints `{{ breadcrumb }}`
  directly, so the embed form silently renders an empty band.
- **Canvas skips a component whose props it cannot build a widget for.** An `array`-typed prop is
  enough to keep an SDC out of Canvas entirely, with no error — it just never appears in
  `canvas.component.sdc.*`. If a component has to be placeable, its props must all be simple
  scalars and anything list-shaped has to be a slot. Check with
  `drush cr && drush sqlq "SELECT name FROM config WHERE name LIKE 'canvas.component.sdc.mcc_theme%'"`.
- **Never surface a leader's own contact details.** The detail page's sidebar routes through the
  office form, exactly like a bio page. `field_email` and `field_phone_number` sit behind
  `field_publish_contact`, which is off for everybody, and this page must not become a second path
  around that gate.
- **Deploying this needs four script runs per environment, after the config import.** The Canvas
  tree, the group terms, the field *values* and the bundle conversions all live in the database,
  so a push to `main` deploys none of them. Once the Quicksilver `config:import` has landed the
  fields and views:

  ```sh
  for s in ministry-groups ministries-content ministries-cleanup ministries-page; do
    ddev exec terminus remote:drush mcc2026.dev -- php:script scripts/$s.php
  done
  # and the category icons, whose field was renamed rather than retyped:
  ddev exec terminus remote:drush mcc2026.dev -- php:script scripts/mcc_setup_category_styles.php
  ```

  All five are idempotent, and `ministries-page.php` aborts with a readable message if the Canvas
  components it needs are not registered yet (run `cache:rebuild` first if so).
- **A field storage's `type` cannot change in place, so config:import cannot carry a retype.**
  `FieldStorageConfig::preSave()` throws on it. Doing it locally through the API works and looks
  fine; the failure surfaces on the next environment's import, which here is automatic and
  server-side. Rename the field instead — a delete plus a create is something import handles —
  and reseed the values with a script. This is how `taxonomy_term.field_icon` became
  `field_category_icon`.
- **A deleted Canvas component leaves its `canvas.folder` entry behind.** Folders track items by
  id rather than through Drupal's config dependency system, so nothing cleans up after a
  component delete and the stale reference rides into the next environment's import. Check
  `grep -rn "<component-id>" config/sync/canvas.folder.*` after removing one.
- **Moving a node between bundles is table surgery and there is no API for it.**
  `scripts/ministries-content.php` does it for the five ex-`page` ministries: `node.type`,
  `node_field_data.type`, and the `bundle` column on every `node__*` / `node_revision__*` table.
  It aborts if any field holding data for those nodes is absent from the target bundle, rather
  than stranding the rows. Aliases survive because `scripts/ia-page-slugs.php` pins them with
  `PathautoState::SKIP` — re-run it after any bundle change to be sure.

