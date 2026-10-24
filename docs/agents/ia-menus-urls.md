# Information architecture, menus and URLs

_Part of the agent guidance indexed in [AGENTS.md](../../AGENTS.md). Read it before working on this area._

The site's IA is five flat nav items plus a Get Involved CTA: **I'm New · About · Ministries ·
Sermons · Calendar**. Missions is *not* a peer of Ministries — missions is a ministry, so it
lives at `/ministries/missions`. `CONTENT_LOG.md` records every content and structure change
made outside of code, and is the first thing to read before re-running a migration.

**I'm New and Get Involved are two different jobs, and were once the same page.** Canvas pages
3 and 11 held identical content under two names, and page 11's `h1` was "Get Involved at MCC" —
it was Get Involved content wearing the wrong label. Page 11 became `/get-involved`, page 3 was
retired (`scripts/ia-get-involved-merge.php`), and a real `/im-new` was built for first-time
visitors: service times, what a Sunday looks like, kids, and how to find the building
(`scripts/ia-im-new-page.php`). Keep them distinct — I'm New is for someone who has never
walked in, Get Involved is for someone deciding where to serve.

- **Don't put unverified specifics on `/im-new`.** Everything on it traces to something already
  on the site (the footer menus carry the address, times, phone; the ministry nodes establish
  the nursery, Kids Worship, Sunday School and youth; the recurring calendar event establishes
  the Facebook Live stream). Parking, which door to use, how long the service runs and the
  communion practice are **not** recorded anywhere and are not safe to invent about a real
  congregation — `ia-im-new-page.php` has a commented `PARKING` constant for when the church
  confirms.

- **One primary menu: `main`.** `mcc_theme_preprocess_page()` reads it. There used to be a
  second `header-nav` menu that only the theme read, so the menu an editor reaches first at
  `/admin/structure/menu` did nothing to the page. Don't reintroduce a theme-private nav menu.
- **Four footer menus, one per footer column**, in this order: `footer-organization` (Visit),
  `footer-ministries`, `footer-connect`, `footer-about` — the order is the only part in code
  (`MCC_FOOTER_COLUMN_MENUS`). **The column heading is the menu's own label**, so renaming
  "Visit" at `/admin/structure/menu` renames the column; the page carries a
  `config:system.menu.*` cache tag for each so the rename shows without a cache clear. A menu
  with no links is dropped rather than rendered as a heading over a hole.
  `scripts/footer-menus.php` declares all four, but writes labels only on create or when the
  menu still carries its pre-design name — a declarative script must not stomp a heading an
  editor renamed. The fifth menu, `footer-support`, is gone — see `CONTENT_LOG.md`.
- **No footer text is hardcoded, and it must stay that way.** Link labels are menu links,
  headings are menu labels, the signup field and button are the `newsletter_email_signup`
  webform, and the six remaining strings (wordmark, tagline, newsletter heading and blurb, both
  legal lines) are theme settings, exposed by `theme-settings.php` at
  `/admin/appearance/settings/mcc_theme`. The `MCC_FOOTER_*` constants in `mcc_theme.theme` are
  fallbacks, not the source: `theme_get_setting()` returns NULL for a key never saved and `''`
  for one an editor cleared, and only NULL falls through to the constant — clearing the contact
  line is how you hide it. Use `[year]` in the legal line rather than writing a year.
- **The newsletter signup is the footer's first row, not a band of its own.** It was an
  `mcc-newsletter-band` SDC in a green band above the footer; the design handoff puts it inside
  the walnut footer above a hairline rule, so that component is deleted and its markup, props
  and webform CSS all live in `mcc-footer`. The webform-specific rules there are load-bearing:
  the email field ships a `size` attribute worth ~500px of intrinsic width, and the submit is
  styled `display:flex; width:100%` further up the cascade. Without the overrides the field
  scrolls every page of the site sideways on a phone and the button drops onto its own line.
- **Every band on a Canvas page is a `section` component carrying a `section_id`.**
  The band's background and vertical padding come from that section, and its colour
  is applied in `mcc_theme/css/mcc-landing-bands.css`, keyed to the id. The front
  page, `/get-involved`, `/im-new` and `/ministries` all work this way. Don't paint a
  band with the `backgroundcolor` prop's `bg-*` classes: `mcc_theme` remaps
  caresphere's `--background`/`--foreground` onto a light palette, so `bg-white`
  resolves to *near-black* and `bg-black` to oatmeal. Ten Canvas pages rely on the
  current behaviour, so repointing those classes is a site-wide job, not a page one.
- **Only `section`, `section-grid` and `hero-card` emit `data-component-id`.** That
  attribute rides on `{{ attributes }}`, and most caresphere components — `button`,
  `section-intro`, `stat-card`, `card-icon`, `text`, `image`, `blockquote` — never
  print it. A rule like `div[data-component-id="caresphere_theme:button"]` is dead
  CSS that looks alive. A block of exactly these accumulated in
  `global-overrides.css` and silently did nothing, including the rule that was
  supposed to paint the front page's closing CTA band. Check the component's Twig
  before writing such a selector.
- **Derive colours with `color-mix(in oklab, …)`, never `in oklch`.** Both of this
  brand's base colours are warm and low-chroma, and oklch's polar hue interpolation
  drifts them badly: oatmeal mixed with white resolved to a pale pink, and oatmeal
  mixed with walnut resolved to rose (`--neutral-100` was `#dbc9c1` instead of
  `#d5ccbe`). The whole neutral ramp and `--white-warm` are oklab for this reason.
- **Section landing pages are Canvas pages; listings are Views *blocks* embedded in them.** No
  View owns a route. Canvas discovers `views_block:*` as components automatically —
  `block.settings.views_block:*` carries `FullyValidatable` in core's schema, which is the gate
  `BlockComponentDiscovery::checkRequirements()` enforces — so this needs no glue code.
  Two deliberate exceptions: `/calendar` stays a controller (a Views display cannot produce the
  month grid), and `/about/leadership` keeps its Views page display because it is a two-view
  construction with custom templates and per-term render cache keys. `/ministries` is the same
  two-view construction as `/about/leadership` but stayed a Canvas page — the terms view is a
  *block* display embedded in it, which is the shape to copy for anything similar.
- **Every bundle has its own pathauto pattern.** The old `menu_path` pattern
  (`/[node:menu-link:parents:join-path]/[node:title]`) had *empty* selection criteria, so it
  applied to every bundle and tied each node's URL to its menu placement — adding a menu link
  silently rewrote aliases. It is deleted. Don't add a pattern without selection criteria.
- **Setting a `path` field inserts a second alias; it does not update the existing one.** This
  is true for both nodes and canvas pages, and it bites twice: `redirect.auto_redirect` hooks
  `redirect_path_alias_update()`, which never fires on an insert, and the stale alias then
  *shadows* any redirect you add by hand, because redirects are matched only after inbound path
  processing has resolved the alias. Always delete the old alias first, then write the 301.
  Pathauto's own `updateEntityAlias()` does update in place and does mint the redirect.
- **Point redirects at `/node/N` or `/page/N`, never at an alias.** Drupal resolves the
  canonical alias at request time, so they survive every future slug change.
- **Canvas validates component inputs strictly.** `items_per_page` must be null or an integer;
  the string `'none'` fails. `heading` is a rich-text prop needing
  `['value' => …, 'format' => 'canvas_html_inline']`, not a bare string.

