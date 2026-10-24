# Icons

_Part of the agent guidance indexed in [AGENTS.md](../../AGENTS.md). Read it before working on this area._

**One icon system: core's Icon API over the Lucide pack.** `drupal/lucide` provides the pack and
`lucide-icons/lucide-static` the ~1,900 SVGs (a `type: drupal-library` package declared in the root
`composer.json`'s `repositories`, so Pantheon's Integrated Composer installs it server-side too).
`ui_icons_field` gives the `ui_icon` field type and `ui_icons_picker` the searchable widget.

- **In a template it is `{{ icon('lucide', 'church', { size: 22 }) }}`.** Don't inline an `<svg>`,
  don't add a Twig map of path data, don't add another icon library. Three separate mechanisms had
  accumulated before this rule existed — editor-uploaded SVG media tinted through `mask-image` for
  the calendar categories, hand-drawn media entities for the About cards, and one-off inline
  `<svg>` in the header, the print button and the photo placeholder.
- **Which icon is content; the drawing is code.** A bundle that needs an icon gets a `ui_icon`
  field with `allowed_icon_pack: [lucide]`, not a `nid → icon` map in the theme. Values are stored
  as a `pack:name` id (`lucide:heart-handshake`), and templates split on the colon.
- **The output strokes in `currentColor`,** so a wrapper's `color` tints it. That is why the
  category chip no longer needs `mask-image` and a `--icon-url` round trip.
- **One system is still outstanding:** the About page's card icons are `svg_image` media entities
  referenced from caresphere's `card-icon` Canvas prop, which expects media. Converting them means
  changing that component's contract across ten Canvas pages, so it is deliberately not done yet.
  Don't add a *fourth* mechanism in the meantime.

