<?php

/**
 * @file
 * Rebuilds the /ministries Canvas page (id 8): strips leftover CareSphere
 * demo bands, fixes a broken hero image, and normalizes padding/contrast to
 * match the /get-involved and /im-new landing pages.
 *
 * Run with:
 *   ddev drush php:script scripts/ministries-structure.php
 *
 * What was wrong
 * ---------------
 * Unlike /get-involved and /im-new (see scripts/get-involved-structure.php),
 * this page's component tree nests correctly. The bug here is worse: a
 * page-wide background/text-colour inversion that makes most of the page's
 * real content nearly invisible, plus leftover CareSphere demo bands.
 *
 * mcc_theme's global-overrides.css remaps caresphere's --background to
 * var(--surface-page) (light) and --foreground to var(--text-body) (dark) —
 * the opposite of caresphere's own dark-first assumption, where .bg-white
 * uses var(--foreground) and .bg-black uses var(--background). Net effect,
 * confirmed by evaluating getComputedStyle() in a real browser against this
 * page:
 *
 *   .bg-black      -> rgb(233, 227, 213)  (light oatmeal, NOT black)
 *   .bg-white      -> oklch(0.22 ...)     (dark near-black, NOT white)
 *   .bg-dark-gray  -> rgb(27, 31, 29)     (genuinely dark — not swapped)
 *
 * Every section on this page with backgroundcolor unset or "white" was
 * therefore silently rendering as a near-black band behind default dark
 * text — invisible. That includes the "Where people serve at MCC" intro +
 * the entire ministries Views listing (both were `dark-gray`, so *genuinely*
 * near-black — same effect via a different route), the Ephesians CTA
 * (measured contrast ratio 1.16), and the pillar-cards band. Get-involved
 * and im-new never hit this because every one of their light bands already
 * carries the `background-color: var(--surface-page)` override in
 * mcc-landing-bands.css (originally added to fix a similar issue on
 * get-involved-serve/-faq) — this page just never got the same treatment.
 *
 * Fixed by giving every section a `section_id` and, in mcc-landing-bands.css,
 * pointing the light ones at `var(--surface-page)` and the two intentionally
 * dark ones (hero, closing CTA) at real brand colours (green-900,
 * terracotta-900) with matching text-color overrides — the same pattern
 * get-involved/im-new already use for their hero/contact bands.
 *
 * Also:
 * - Hero `background_image` pointed at media #7, whose file is a PDF
 *   ("TaxesPDF.pdf") with an unrelated alt text — a stray migration
 *   reference, not a real photo. Dropped in favour of a flat colour band,
 *   same treatment as the other two landing pages' heroes.
 * - The closing CTA was `backgroundcolor: black`, which (per the swap above)
 *   was accidentally rendering light with forced-dark !important text —
 *   readable by coincidence, not design. Switched to `dark-gray` + a real
 *   terracotta-900 override, matching get-involved-contact/im-new-visit.
 * - A bare "Supporting communities, transforming lives" heading and a
 *   6-slide stock-photo carousel (captions about "protecting our planet",
 *   "no one should sleep hungry" — nothing about this church) are pure
 *   CareSphere demo filler. Dropped.
 * - A "pillar-cards" band ("Neighborhood strengthening" etc.) was also demo
 *   content, and its primary CTA linked to /impact, which 404s. Kept the
 *   band but replaced the three cards with real ministry entry points.
 * - An orphan trailing image (a bio headshot screenshot, mislabelled with a
 *   café-photo alt text) sat alone in a black section after the closing CTA,
 *   with no caption. Dropped.
 * - Several sections used paddingtop/bottom 14 (7rem); get-involved/im-new
 *   standardized on 10 (5rem) as heavier than the rest of the site warrants.
 *   Normalized here too.
 *
 * Idempotent: the tree is declared in full below and replaces whatever is
 * stored, so re-running after a migration re-import restores the same
 * result.
 */

// The desired tree, depth first. Each entry is [uuid, slot, children].
// A NULL slot means top level.
$tree = [
  // Hero.
  ['12ac7f15-fdbc-4fbb-8cba-8433b36e3909', NULL, [
    ['0b7e5079-560b-436d-b27e-77205eb9d56d', 'content', [
      ['570fb1f1-3668-4286-999b-6f0b9797333a', 'ctas', []],
    ]],
  ]],
  // Where people serve at MCC — intro band.
  ['f6ab85d1-a936-4205-b9ca-17bca2cfdc68', NULL, [
    ['f7e3e605-abc9-4e14-adc3-750b3cf6b1d3', 'content', []],
  ]],
  // The real ministries listing (Views block).
  ['ebe50929-818b-4ec4-9769-2ee0d0d831b5', NULL, [
    ['dcc1a116-a5af-4fb8-8c0a-cf2c8939523a', 'content', []],
  ]],
  // Ephesians 4:11-13.
  ['dc5c78f2-0c52-45f0-b5c6-f91974d320d6', NULL, [
    ['50b6934b-45b4-49ab-b56b-654f95660270', 'content', []],
  ]],
  // Pillar cards — real ministry entry points, replacing demo content.
  ['e517cd70-8c38-4edd-81fd-9efe5007b171', NULL, [
    ['9005bd76-d7d1-4f8c-a3f8-5cc81d8b89ea', 'content', []],
  ]],
  // Closing CTA.
  ['b58f2169-cea5-496b-a747-c5720ce91bd2', NULL, [
    ['292425c4-634a-491c-a85e-5ad6a27a8754', 'content', [
      ['508b6cd8-2e84-4d6d-85c8-c10a6e727d68', 'ctas', []],
      ['9af1c0b0-b70d-4f5a-84a3-ffbafff19a06', 'ctas', []],
    ]],
  ]],
];

// Everything else on the page (the demo heading, the demo slider and its six
// images, and the orphan trailing image) is dropped by omission.

// Input overrides applied on top of what is stored, keyed by uuid.
//
// Padding: paddingtop 14 => 7rem, matching what get-involved-structure.php
// found too heavy; 10 => 5rem (--space-20) is the norm elsewhere on the site.
$overrides = [
  // Hero: drop the broken PDF "background image", get a real section_id so
  // mcc-landing-bands.css can fix its text contrast and give it the same
  // green-900 the other two landing pages' heroes use.
  '12ac7f15-fdbc-4fbb-8cba-8433b36e3909' => [
    'section_id' => 'ministries-hero',
    'background_image' => NULL,
    'paddingtop' => 10,
    'paddingbottom' => 10,
  ],
  // The hero button was `variant: primary`, which fills with --color-primary
  // (green-700) — a near-monochrome clash once the hero is actually
  // green-900 instead of its former accidental oatmeal. secondary-white
  // (transparent, white border/text) is the variant this component library
  // already uses for buttons on dark bands elsewhere.
  '570fb1f1-3668-4286-999b-6f0b9797333a' => [
    'variant' => 'secondary-white',
  ],
  // Was `dark-gray` (genuinely near-black per the swap above), which is why
  // the "Where people serve" heading and tagline were nearly invisible.
  // `white` + the CSS surface-page override make this an actual light band.
  'f6ab85d1-a936-4205-b9ca-17bca2cfdc68' => [
    'section_id' => 'ministries-intro',
    'backgroundcolor' => 'white',
    'paddingtop' => 10,
  ],
  // The section-intro's own `textcolor: light` matched the (accidentally)
  // dark band it used to sit on; now that the band is genuinely light, light
  // text on it would be the same invisibility bug in reverse. Remove it.
  'f7e3e605-abc9-4e14-adc3-750b3cf6b1d3' => [
    'textcolor' => NULL,
  ],
  // Same story: `dark-gray` made the entire ministries listing (six
  // headings, descriptions, and bullet lists) render as dark text on a
  // near-black band — measured contrast ratio 1.05, i.e. invisible.
  'ebe50929-818b-4ec4-9769-2ee0d0d831b5' => [
    'section_id' => 'ministries-listing',
    'backgroundcolor' => 'white',
    'paddingbottom' => 10,
  ],
  // Backgroundcolor was unset, which resolves to the `white` class — and per
  // the swap above, `.bg-white` computes to near-black. Measured contrast
  // ratio on this heading: 1.16. Made explicit and given the same
  // surface-page override as every other light band.
  'dc5c78f2-0c52-45f0-b5c6-f91974d320d6' => [
    'section_id' => 'ministries-verse',
    'backgroundcolor' => 'white',
    'margintop' => 10,
  ],
  // Same unset-resolves-to-invisible-dark issue as the Ephesians band above.
  // The cards' own light `var(--card)` background mostly masked it, but the
  // section's padding was still bleeding dark. Also: was left-aligned with
  // nothing to align (no section-intro), so center it like every other band.
  'e517cd70-8c38-4edd-81fd-9efe5007b171' => [
    'section_id' => 'ministries-pillars',
    'backgroundcolor' => 'white',
    'alignment' => 'center',
    'marginbottom' => 10,
  ],
  // Closing CTA: was `backgroundcolor: black`, which forces the same
  // dark-on-dark `!important` text color as the hero. get-involved-contact
  // and im-new-visit both use `dark-gray` instead — no forced text color, so
  // the terracotta override just needs to win on ID specificity, not
  // !important. Match that rather than fighting bg-black here too.
  'b58f2169-cea5-496b-a747-c5720ce91bd2' => [
    'section_id' => 'ministries-cta',
    'backgroundcolor' => 'dark-gray',
    'paddingtop' => 10,
  ],

  // Pillar cards: three real entry points into the ministries the page
  // exists to surface, replacing the CareSphere "Neighborhood strengthening"
  // demo copy and its dead /impact link. No icons — the demo's three copies
  // of the same globe glyph weren't worth keeping, and the component reads
  // fine without one (see pillar-cards.twig).
  '9005bd76-d7d1-4f8c-a3f8-5cc81d8b89ea' => [
    'card_1_media' => NULL,
    'card_1_heading' => 'Worship & Discipleship',
    'card_1_description' => [
      'value' => 'Sunday mornings, Sunday School, and nursery care that ground our whole church family in God\'s Word.',
      'format' => 'canvas_html_block',
    ],
    'card_1_primary_label' => 'Worship Service',
    'card_1_primary_url' => '/ministries/worship-service',
    'card_1_secondary_label' => 'Youth Ministry',
    'card_1_secondary_url' => '/ministries/youth',

    'card_2_media' => NULL,
    'card_2_heading' => 'Care & Community',
    'card_2_description' => [
      'value' => 'Practical support for families in our congregation, and a place for women in the church to grow together.',
      'format' => 'canvas_html_block',
    ],
    'card_2_primary_label' => 'C.A.R.E. Ministry',
    'card_2_primary_url' => '/ministries/care-christians-are-reaching-everyone-ministry',
    'card_2_secondary_label' => "Women's Ministry",
    'card_2_secondary_url' => '/ministries/womens',

    'card_3_media' => NULL,
    'card_3_heading' => 'Missions & Outreach',
    'card_3_description' => [
      'value' => 'Local emergency response and gospel work here and around the world.',
      'format' => 'canvas_html_block',
    ],
    'card_3_primary_label' => 'Our Missions',
    'card_3_primary_url' => '/ministries/missions',
    'card_3_secondary_label' => 'Emergency Response',
    'card_3_secondary_url' => '/ministries/emergency-response',
  ],
];

$page = \Drupal::entityTypeManager()->getStorage('canvas_page')->load(8);
if (!$page) {
  print "no canvas_page 8 (/ministries)\n";
  return;
}

// Index what is stored so component_id/version/label survive the rebuild.
$stored = [];
foreach ($page->get('components') as $item) {
  $value = $item->getValue();
  $stored[$value['uuid']] = $value;
}

$items = [];
$missing = [];

$walk = function (array $nodes, ?string $parent) use (&$walk, &$items, &$missing, $stored, $overrides) {
  foreach ($nodes as [$uuid, $slot, $children]) {
    if (!isset($stored[$uuid])) {
      $missing[] = $uuid;
      continue;
    }
    $value = $stored[$uuid];
    $value['parent_uuid'] = $parent;
    $value['slot'] = $slot;

    if (isset($overrides[$uuid])) {
      $inputs = json_decode($value['inputs'] ?: '{}', TRUE) ?: [];
      foreach ($overrides[$uuid] as $key => $new) {
        // A NULL override means "unset this input and let the SDC default
        // win" — including a nested array value like card_N_description.
        if ($new === NULL) {
          unset($inputs[$key]);
        }
        else {
          $inputs[$key] = $new;
        }
      }
      $value['inputs'] = json_encode($inputs);
    }

    $items[] = $value;
    if ($children) {
      $walk($children, $uuid);
    }
  }
};
$walk($tree, NULL);

if ($missing) {
  print "aborting — these uuids are not on the page: " . implode(', ', $missing) . "\n";
  return;
}

$dropped = array_diff(array_keys($stored), array_column($items, 'uuid'));

$page->set('components', $items);
$page->save();

printf(
  "rebuilt /ministries: %d components (was %d)\n",
  count($items),
  count($stored)
);
foreach ($dropped as $uuid) {
  printf("  dropped %s (%s)\n", $uuid, $stored[$uuid]['component_id']);
}
