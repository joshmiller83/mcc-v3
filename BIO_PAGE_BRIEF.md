# Design brief: Bios / Our Leadership

## What we need

Mechanicsburg Christian Church has a `bio` content type carrying staff, elders,
deacons, trustees, and ministry leaders. It needs two designed surfaces: a
**listing page** that lets a visitor scan the people who lead the church and find
the right one, and a **detail view** for an individual person. The listing
already has a home at `/who-we-are/our-leadership`, driven by a view called
`mcc_leadership` titled "Our Leadership." It renders today (HTTP 200) but it is
raw Views output — an unstyled vertical stack of nodes with labels above every
field. There is no `node--bio.html.twig` in `mcc_theme` or in its base theme
`caresphere_theme`, so nothing about the current presentation is intentional.
Treat it as a blank slate rather than something to refine.

The audience is congregation members and newcomers, and the job the page does is
mostly reassurance and wayfinding: *who runs this ministry, who do I talk to
about grief care, is there a face I recognize.* That argues for a scannable,
photo-forward layout with clear role labels, rather than a dense directory.

## The content model

A `bio` node has a title plus six fields. `field_bio_pic` is a single-value
entity reference to a media image, labeled "Photo." `body` is a
`text_with_summary` field labeled "Biography" (summary disabled).
`field_email` is an email field and `field_phone_number` is a telephone field,
both single-value. `field_ministry_structure` is an entity reference to the
`mcc_leadership_structure` taxonomy, labeled "Leadership Structure" and now
**multi-value**, because several people sit in two groups. `field_attachments`
is an unlimited entity reference to media documents, currently unused by any bio.

Every field is presently configured `label: above` with stock formatters, which
is another sign that nobody has designed this yet — ignore the current display
settings entirely.

One content-quality issue to design around: **the title field mixes name and
role.** Real titles look like `Brian Hoffman -  Emergency Response Team`,
`Pat Garland - Communications & Women's`, `Alan Martin Finance `, and
`Jim Garland ` — inconsistently delimited, sometimes with trailing whitespace,
sometimes with no delimiter at all. A design that assumes a clean "Name" plus a
separate "Role" needs that data split first. Flag it if your design depends on
it; it is a content cleanup task, not something to paper over.

## The actual content — design to these numbers

The bio content is fully migrated from the legacy Drupal 7 site as of this
writing, and 18 lorem-ipsum placeholder bios have been purged. **There are
exactly 20 real people.** The fill rates are uneven and that unevenness is the
central design constraint:

| Field | Populated | Of 20 |
|---|---|---|
| Photo | 19 | almost all |
| Leadership structure | 20 | all |
| Phone number | 14 | most |
| Email | 6 | under a third |
| Biography prose | **3** | almost none |
| Attachments | 0 | none |

The biography number deserves emphasis, because it is the one most likely to
break a design. Only **three** people have written prose — 1,268, 987, and 514
characters respectively. A fourth record contains the literal string `<p>-</p>`
and should be treated as empty. **A layout that leads with a paragraph of
biography will be empty for 17 of 20 people.** Please design the photo, name,
role, and leadership group as the primary content, and treat prose as a
progressive enhancement that appears for the rare person who has it. If the
detail view is mostly a large empty text column, the design has failed.

The leadership taxonomy is well populated and is the most reliable grouping axis
available. The distribution across the 20 bios is: Ministry Leaders (11),
Deacons (6), Elders (3), Trustees (3), Senior Minister (1) — 24 assignments
total, because four people hold two roles. That is a workable set of groups: one
large bucket, three small ones, and a single-person group that probably deserves
distinct emphasis rather than being rendered as a group of one.

## Photography

Portraits are the backbone of this page and they are in good shape. Focal points
were migrated from the legacy site's Manual Crop data, and the bio photos have
been verified end to end — node reference resolves to a media entity, to a file
that exists on disk, with a focal point recorded. The legacy headshot crop was
130×180, roughly 2:3, and migrated focal points cluster around 45–50%
horizontally and 35–50% vertically, meaning faces sit slightly above center.

Ready-made focal-point image styles exist at 1:1 (300–960px), 2:3 (200×300
through 640×960), and 3:4 (225×300 through 720×960), all WebP. **Pick any ratio
you like** — the focal point makes the crop safe, and you are not constrained to
the legacy 2:3. This is the one area where you have complete freedom.

Exactly one person has no photo, so the empty state matters but is not
load-bearing. An `mcc-photo-placeholder` component exists in the theme; for a
person, a monogram or silhouette will likely read better than a generic photo
placeholder. Specify which you want.

## Design system to work within

The theme is `mcc_theme`, inheriting from `caresphere_theme`. The palette is warm
and earthy: primary green `#2f4833` (`--green-700`), secondary terracotta
`#ae5b33` (`--terracotta-500`), plus walnut `#4e3426`, a brick red for
destructive states, and an oatmeal `#e9e3d5` that serves as the default page
surface — the site is not white-backgrounded. Typography pairs **Calistoga** as
the display serif with **Nunito** as the body sans, on a scale running from
`--text-display-xl` (4rem) through `--text-heading-*` and `--text-body-*` down to
`--text-caption` (0.75rem), with `--tracking-eyebrow` at 0.08em for small
caps-style labels. Weights are 400 / 600 / 700 / 800. All of this lives in
`web/themes/custom/mcc_theme/css/tokens/`; compose from those tokens rather than
introducing new values.

For structural precedent, look at the existing MCC components — `mcc-event-card`,
`mcc-events-grid`, `mcc-this-week`, `mcc-header`, `mcc-footer`, and
`mcc-newsletter-band`. The event card establishes the card idiom this site uses,
and a person card should feel like a sibling to it rather than a new language.
The `caresphere_theme` base also provides Canvas SDCs including `hero-card`,
`pillar-cards`, `section`, `section-grid`, `section-intro`, and `section-stories`
if a section-based composition suits the listing better than a bespoke grid.

## Questions worth answering in the design

Grouping is now a real choice rather than a guess: should the listing render five
labeled groups following the leadership taxonomy, or one flat grid with the group
shown as a chip on each card? The taxonomy is fully populated, so either works —
but note the lopsided distribution and the four people who would appear twice
under a grouped layout, which needs a rule.

Second, given that only three people have prose, does an individual bio warrant
its own page at all? A card that expands in place, or a listing rich enough to be
self-sufficient, may serve better than 20 detail pages that are mostly a photo
and a phone number. Make a recommendation.

Third, and worth deliberate thought rather than a default: **email addresses and
phone numbers are personal contact details for named individuals**, and 14 of
these people have a phone number on file. Publishing those on a public page is a
real decision, not a formatting question. Consider whether the design should
route through a contact form, show a role-based address, or expose direct details
only where someone has opted in — and make that recommendation explicitly rather
than leaving it to implementation.
