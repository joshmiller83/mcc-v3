# The calendar

_Part of the agent guidance indexed in [AGENTS.md](../../AGENTS.md). Read it before working on this area._

`/calendar` and `/calendar/print` are built from fielded entities and config — see the "The calendar" section of `README.md` for the shape of it. When working on them:

- **Never hard-code a category's colour, shape or name** in PHP, Twig or CSS. Colour comes from `field_category_color` on the *Mission category* term and is passed down as a `--evt` custom property; shape comes from `field_marker_shape` and is passed down as a `data-shape` attribute. A new category must be addable from the admin UI alone.
- **Resolve category styling through `EventContext`**, not by reading the term's fields again somewhere else. It's the single place the fallback colour/shape and the all-day and short-time rules live.
- **Build the month grid with the `mcc_core.calendar_month` service.** Both controllers use it, so the screen and print views can't drift apart; multi-day bands are derived there from consecutive days rather than stored on the node. The front page's "This week at MCC" panel goes through the same service (`CalendarMonth::week()`) and renders the calendar's own `mcc-calendar-week` component — don't give it a second way to find or lay out events. A Views display can't produce that shape (positioned day columns, derived bands, lane packing), which is why the panel is a block plugin over the service rather than a view.
- **Re-run `node scripts/calendar-compare.mjs` before committing.** The print sheet fitting on one page is a hard requirement, and it's easy to break from a distance (a base-theme `p { font-size }` rule was enough to do it once).

## The event detail page

`node--calendar-event--full.html.twig` over `_mcc_theme_event_context()`, styled by
`css/mcc-event-detail.css`.

- **A media well positions its contents absolutely; `height: 100%` does not reach the `<img>`.**
  Rendered media arrives inside several static wrappers (field, media article, field again,
  `<picture>`), so a percentage height on the outermost one resolves to `auto` by the time it gets
  to the image. The hero photo rendered at its natural 16:9 — 656px in a 437px well — and because
  the well was `position: relative` and the title block below it was not, the photo painted *over*
  the title, chip and date badge. Copy `.mcc-person-card__photo`: `overflow: hidden` on the well,
  `position: absolute; inset: 0` on its child and on the `img`, and `.contextual-region` back to
  `position: static` so editors don't get a zero-height containing block.
- **Anything pulled over a positioned well with a negative margin must be positioned too.** The
  date badge sat underneath the hero media for exactly this reason, and it showed even with no
  photo: white "MAR" floating on the then-placeholder with the day cut off.
- **No photo means no photo area — never a placeholder.** Most events have no image, and the
  church wants that to read as finished, not as a "Photo coming soon" box. The template leaves
  the media well out entirely and adds `mcc-event__hero--no-photo`, which turns the header into
  a title band: a rule in `--evt` on top, the date badge beside the headings with no pull-up.
  `mcc-photo-placeholder` itself stays — `scripts/homepage-structure.php`'s Canvas photo slots
  still use it.
- **Match `img` in a media well, not `svg`.** The old `:is(img, svg, canvas)` rule also caught the
  photo placeholder's 28px Lucide icon and stretched it, back when the well had one.
- **Never wrap a rendered teaser in an `<a>`.** `mcc-person-card` is already one big link. The
  speaker list wrapped each card in a second anchor; HTML cannot nest them, so the parser closed
  the outer one early, leaving an empty padded box with the portrait spilled out below it — over
  the "Files & flyers" list on desktop, over the "Event details" card on a phone. Twig rendered
  exactly what it was told, so nothing server-side looks wrong; only the browser's DOM shows it.
- **An occurrence with no end time is worded as a start time alone, in
  `EventContext::describeOccurrence()`.** The D7 migration carried only a start for 3,105 of 3,548
  occurrences, so end equals start and every list read "6:00 PM – 6:00 PM". Fix wording there, not
  in a template — the detail page, its sidebar lists and the calendar all read that one label.
- **The hero uses the media `16_9_wide` view mode.** `16_9_large` tops out at a 960px derivative,
  which is soft across an 1166px hero. Same 16:9 focal-point crop, so an editor's focal point
  behaves identically.
- **Screenshots need a one-time browser install on a fresh Codespace:**
  `npx --yes playwright install chromium`, then
  `sudo env "PATH=$PATH" npx --yes playwright install-deps chromium` — plain `sudo npx` fails
  with "command not found" because sudo drops the PATH that node lives on.
  `scripts/calendar-compare.mjs` finds the same binary. Shoot every page twice, anonymous and
  through a `ddev drush uli` session, and don't trust `fullPage` captures for editor chrome: the
  admin sidebar appears to cover the content in them and does not in a real viewport.

