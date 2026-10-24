# Announcements

_Part of the agent guidance indexed in [AGENTS.md](../../AGENTS.md). Read it before working on this area._

The front page's "Coming up at MCC" band is the D7 "Homepage Teaser" nodes, migrated into the
`announcement` type — title, a picture in `field_featured_image`, an optional `field_link`, a
body — and listed by the `mcc_announcements` view. See the "Announcements" section of
`README.md` for the shape of it. When working on them:

- **The band is a Views block in the Canvas tree, placed by `scripts/homepage-structure.php`.**
  `section` `home-announcements`, directly after the hero, holds a `section-intro` heading and
  `views_block:mcc_announcements-flyers`; the band's colour and rhythm are keyed to that id in
  `mcc-landing-bands.css`. The view does the choosing — published, has a picture, sticky first,
  then newest — so nothing in code names an announcement and the band empties itself when the
  church has none. No page display, no route. The rows are Views' plain unformatted
  `.views-row`s, laid out by `mcc-landing-bands.css` as **one row that never reflows**: five
  across on a desktop, and narrower than that the row scrolls sideways with scroll snapping — a
  carousel's behaviour with no JavaScript. The first cut was core's Responsive Grid at three
  columns; the cards were huge and the five reflowed to 3 + 2, and the church asked for one row.
- **The card is `mcc_theme:mcc-announcement-card`: scalar props and two slots.** `title`, `url`,
  `link_label`, `heading_level`; the picture and the body are slots filled from the node's
  teaser display, so which image style and which text format apply stay admin settings. An
  `array`-typed prop would keep it out of Canvas entirely (see Ministries).
- **The picture renders uncropped, at its own shape, through the media `medium` view mode**,
  which is the `uncropped_medium` responsive style. The pictures are whatever an editor attached
  (a book cover, a road sign, a square graphic), and a 16:9 or 3:4 crop takes the title line off
  a cover. Nothing in the well is positioned, so an editor's `.contextual-region` wrapper changes
  nothing here.
- **Every card links somewhere, and the anchor never wraps the picture or the text.**
  `field_link` when the editor gave one, the announcement's own page otherwise: the card shows
  the first six lines of the body (a CSS line clamp — nothing touches the text) and the page has
  the rest. The title is the `<a>`, stretched over the card with `::after`. The first cut
  wrapped the whole card in the anchor; it was fine anonymously, and logged in the media entity's
  contextual links rendered *inside* it — two nested anchors per linked card. A body can carry
  links of its own, too. `scripts/homepage-review.mjs` counts `#home-announcements a a` in both
  sessions; keep it at zero.
- **The announcement page is `node--announcement--full.html.twig` over the same context,** styled
  by `css/mcc-announcement-detail.css`: the shared `mcc-title-band`, then a layout clamped to the
  container — picture left at its own shape, text right at a reading measure, the link as a
  button only when `field_link` is set (the context carries `link` apart from `url`, which falls
  back to the page's own address for the card). Same shape as the ministry, bio and event pages.
  The church asked for it once the card led here.
- **Nothing is parsed out of the body at render time,** and the link is read in
  `_mcc_theme_announcement_context()` rather than rendered through the link formatter, which
  would hand the component a second anchor.
- **The ones that come over with a flyer are rebuilt by `scripts/announcements-content.php`**:
  five on 2026-10-01, two more on 2026-10-24.
  D7's "image" was a 1275×1650 page of Word text with a picture in one corner, unreadable at card
  size, so the church asked for the picture cropped out and the words moved into the body. The
  crops ship in `mcc_theme/images/announcements/`; the script copies them into the files
  directory (and puts a missing file back, which is what makes a database dump plus the script a
  complete hand-off), mints the media by name, and writes the body. Idempotent, keyed by D7 nid
  through the migrate map with the title as a guard. The flyer media stay in the library. A
  `migrate:import mcc_announcement --update` reverts them all; re-run the script after one.
- **A new D7 teaser that arrives as a flyer gets an entry in that script, not a hand edit.**
  The office keeps making them the same way, so expect more. Crop the picture at its native size;
  nothing is scaled. The trim gives the box: `convert "<flyer>" -crop 600x900+0+0 +repage
  -fuzz 10% -trim -format '%wx%h%O' info:`. If the result runs into the text, narrow the
  600 until the column beside it reads pure white (`-format '%[fx:mean]'` = 1). Save with
  `-strip -quality 90`. Type the words in sentence case. Words printed *inside* the picture go
  in its alt text, since the crop keeps them as pixels.
- **`scripts/homepage-review.mjs` is the check:** the front page at 1280 and 390, anonymous and
  through a `drush uli` session, asserting no horizontal scroll, every picture loaded and inside
  its card, no overlapping cards, no nested anchors. Lazy-loaded pictures below the fold report
  `naturalWidth` 0 until scrolled to, so it scrolls each card into view before measuring — the
  first version failed the anonymous phone pass on exactly that, with nothing wrong on the page.
- **Deploying this to the tip is the dump, not the migration.** `migrate:import` cannot run on
  Pantheon (no `legacy` database), so a local database dump is what carries the announcements,
  their migrate map, the rebuilt flyers, the Canvas tree and the config. Which dump, and the steps
  in order, are in the dated update that "Open items" in [AGENTS.md](../../AGENTS.md) points at.
  The 10-01 recipe that stood here never copied the files the dump's media point at; the
  2026-10-24 one does.
