<?php

/**
 * @file
 * Rebuilds the /get-involved Canvas page tree so components sit inside their sections.
 *
 * Run with:
 *   ddev drush php:script scripts/get-involved-structure.php
 *
 * Why this exists
 * ---------------
 * Every one of the 25 components on this page was stored with a NULL
 * parent_uuid and a NULL slot, so Canvas rendered them as flat siblings: eight
 * empty <section> elements each carrying 7rem of padding around nothing, with
 * the intros, grids, cards and accordions that belong *inside* them emitted
 * after their closing tags. That is the single cause of the empty bands, the
 * invisible hero (white section-intro text landing on the page background
 * instead of on its dark section), the full-width serve cards, and the
 * unstyled accordion list.
 *
 * The `section` and `section-grid` templates are fine — both print their slots
 * via `{% block %}`. Nothing was ever put in those slots. Every other Canvas
 * page on this site nests correctly; pages 3 and 11 are the two that do not.
 *
 * What it builds
 * --------------
 * Five bands, each one section owning its whole contents:
 *
 *   Hero              green      section-intro (h1)
 *   Ways to Serve     oatmeal    section-intro + 3-column grid of icon cards
 *   Find Your Ministry subtle    section-intro + two buttons in its ctas slot
 *   Common Questions  oatmeal    section-intro + 1-column grid of accordions
 *   Still Have …      terracotta section-intro + one button in its ctas slot
 *
 * Three sections are dropped: they only existed as pseudo-containers to work
 * around the missing nesting (one held the card grid, two held button rows).
 * Their padding and gap now belong to the band section that absorbed them.
 *
 * Band colours are keyed off the `section_id` each section gets here, and are
 * styled in mcc_theme/css/mcc-landing-bands.css. They are deliberately *not* done by
 * restyling the `bg-*` classes: mcc_theme remaps caresphere's --background /
 * --foreground to a light palette, which silently swaps what `bg-white` and
 * `bg-black` mean, and all ten Canvas pages on this site rely on the current
 * behaviour. Repointing those classes is a separate job with a much wider
 * blast radius than one page.
 *
 * Idempotent: the tree is declared in full below and replaces whatever is
 * stored, so re-running after a migration re-import restores the same result.
 */

// The desired tree, depth first. Each entry is [uuid, slot, children].
// Slots: sections expose 'content', section-grid exposes slot_1..slot_3, and
// section-intro exposes 'ctas'. A NULL slot means top level.
$tree = [
  // Hero.
  ['777646a3-9c19-49bf-b2b5-e849f6dfc0d0', NULL, [
    ['a8e1f642-a261-4010-b6d4-f8126dfd1c26', 'content', []],
  ]],
  // Ways to Serve — intro plus the three serve cards, one band.
  ['9793de3c-c240-47e7-bf66-c9729819887b', NULL, [
    ['064e6a03-2de7-4963-b067-de64446a1351', 'content', []],
    ['754aa131-287f-493b-abf5-7ac183528fa8', 'content', [
      ['42b78bdd-2630-47fd-b6a5-6de0344c9123', 'slot_1', []],
      ['a60d1a87-11d2-4e21-bc0b-156ae156e729', 'slot_2', []],
      ['21f857b1-f217-43c2-a65d-343caddc8d00', 'slot_3', []],
    ]],
  ]],
  // Find Your Ministry — the two buttons belong to the intro's CTA slot.
  ['77187199-31bf-42ad-9a19-e266b8d7e9c2', NULL, [
    ['7e4ac486-2dbf-455f-ac07-cc6960499403', 'content', [
      ['6ddbd8aa-88d4-4460-a813-f296fc180308', 'ctas', []],
      ['767bd10a-1fbb-4133-b370-28c7118787ff', 'ctas', []],
    ]],
  ]],
  // Common Questions — the four FAQ items stack in the grid's first column.
  ['302a785a-2f28-4c57-b670-5e48fd9f465a', NULL, [
    ['7a4ee946-d9c4-4a23-8d81-cc47c4fc4aad', 'content', []],
    ['aab940a8-4c3e-4013-be2a-e998913aafbf', 'content', [
      ['771124ac-3bf6-45ae-9060-0a59f7ae1afe', 'slot_1', []],
      ['6e3e4d8e-ebe4-48b8-811c-c86a0487da06', 'slot_1', []],
      ['52030003-424a-4307-8e52-82f8c8a6d6b2', 'slot_1', []],
      ['2920734e-e65f-4a6d-873a-ef2ec45fa749', 'slot_1', []],
    ]],
  ]],
  // Still Have Questions.
  ['618c4985-decd-42a4-81aa-fc932a4bb21f', NULL, [
    ['35ba3be0-f9b7-4f80-b8b6-f43a5724335d', 'content', [
      ['4a5c504e-7036-40e3-834d-88f6c1120e69', 'ctas', []],
    ]],
  ]],
];

// Input overrides applied on top of what is stored, keyed by uuid.
//
// Padding: the prop is a multiple of 8px doubled into rem (14 => 7rem), which
// is heavier than the rest of the site. 10 => 5rem, matching --space-20.
// Margin-bottom is zeroed everywhere because the band padding already
// separates the sections — leaving the default 10 stacks margin onto padding.
$overrides = [
  // Hero: deep green, was "black" (near-black, not a brand colour).
  '777646a3-9c19-49bf-b2b5-e849f6dfc0d0' => [
    'section_id' => 'get-involved-hero',
    'backgroundcolor' => 'black',
    'alignment' => 'center',
    'paddingtop' => 10,
    'paddingbottom' => 10,
    'marginbottom' => 0,
  ],
  // Ways to Serve: the page's own oatmeal.
  '9793de3c-c240-47e7-bf66-c9729819887b' => [
    'section_id' => 'get-involved-serve',
    'backgroundcolor' => 'white',
    'alignment' => 'center',
    'paddingtop' => 10,
    'paddingbottom' => 10,
    'marginbottom' => 0,
  ],
  // Find Your Ministry: the subtle warm-white band.
  '77187199-31bf-42ad-9a19-e266b8d7e9c2' => [
    'section_id' => 'get-involved-ministry',
    'backgroundcolor' => 'gray-light',
    'alignment' => 'center',
    'paddingtop' => 10,
    'paddingbottom' => 10,
    'marginbottom' => 0,
  ],
  // Common Questions: back to oatmeal.
  '302a785a-2f28-4c57-b670-5e48fd9f465a' => [
    'section_id' => 'get-involved-faq',
    'backgroundcolor' => 'white',
    'alignment' => 'center',
    'paddingtop' => 10,
    'paddingbottom' => 10,
    'marginbottom' => 0,
  ],
  // Still Have Questions: terracotta, closing the page opposite the green hero.
  '618c4985-decd-42a4-81aa-fc932a4bb21f' => [
    'section_id' => 'get-involved-contact',
    'backgroundcolor' => 'dark-gray',
    'alignment' => 'center',
    'paddingtop' => 10,
    'paddingbottom' => 10,
    'marginbottom' => 0,
  ],

  // The serve cards. tile_size is dropped so the card stops being forced to
  // aspect-square (which is what made them enormous), and border_radius is set
  // so they stop rendering rounded-none — nothing in this brand is 0px.
  // Each card gets its own icon rather than three copies of the same globe,
  // and card 1 gets a real CTA so it is no longer a bare link to nowhere.
  '42b78bdd-2630-47fd-b6a5-6de0344c9123' => [
    'tile_size' => NULL,
    'border_radius' => 'medium',
    'cta_url' => '/ministries',
    'cta_label' => 'Volunteer roles',
    'cta_variant' => 'link',
  ],
  'a60d1a87-11d2-4e21-bc0b-156ae156e729' => [
    'tile_size' => NULL,
    'border_radius' => 'medium',
    // Was /node/35 — a raw node path in content is a maintenance trap.
    'cta_url' => '/ministries/care-christians-are-reaching-everyone-ministry',
    'cta_label' => 'Learn more',
  ],
  '21f857b1-f217-43c2-a65d-343caddc8d00' => [
    'tile_size' => NULL,
    'border_radius' => 'medium',
    'cta_url' => '/ministries/missions',
    'cta_label' => 'Our missions',
  ],

  // Buttons: let the two ministry CTAs and the contact CTA go full width on
  // small screens rather than sitting as two stranded pills.
  '6ddbd8aa-88d4-4460-a813-f296fc180308' => ['mobile_width' => TRUE],
  '767bd10a-1fbb-4133-b370-28c7118787ff' => ['mobile_width' => TRUE],
  '4a5c504e-7036-40e3-834d-88f6c1120e69' => ['mobile_width' => TRUE],

  // "Yes!" -> "Yes." — the voice guide keeps exclamation points for genuine
  // warmth and "We Love People!" already spends the page's one.
  '2920734e-e65f-4a6d-873a-ef2ec45fa749' => [
    'answer' => 'Yes. Our Youth Ministry always needs adult volunteers and mentors. Contact our youth leaders or stop by on a Sunday morning.',
  ],
];

$page = \Drupal::entityTypeManager()->getStorage('canvas_page')->load(11);
if (!$page) {
  print "no canvas_page 11 (/get-involved)\n";
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
        // A NULL override means "unset this input and let the SDC default win".
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
  "rebuilt /get-involved: %d components in 5 nested bands (was %d flat)\n",
  count($items),
  count($stored)
);
foreach ($dropped as $uuid) {
  printf("  dropped empty wrapper %s (%s)\n", $uuid, $stored[$uuid]['component_id']);
}
