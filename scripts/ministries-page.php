<?php

/**
 * @file
 * Rebuilds the /ministries Canvas page (id 8) to the design handoff's geometry.
 *
 * Run with: ddev drush php:script scripts/ministries-page.php
 *
 * What changes, against what scripts/ministries-structure.php left behind:
 *
 * - **Bands 1 and 2 merge into one green title band.** Band 1's heading was
 *   "Our ministries", which is doing nothing a breadcrumb does not already do,
 *   so band 2's "Where people serve at MCC" becomes the h1 and band 1's
 *   eyebrow, lede and button come with it. The band is the mcc-title-band SDC,
 *   shared with every ministry detail page.
 * - **The listing becomes the grouped one.** `views_block:mcc_ministries-block_1`
 *   listed six of eleven ministries, ungrouped, alphabetically — which is why
 *   "Building & Grounds" led the page. It is replaced by
 *   `views_block:mcc_ministry_groups-block_1`, which renders a section per
 *   Ministry Group term in vocabulary order.
 * - **Band 5 (the pillar cards) is deleted.** Those three cards were a
 *   hand-typed stand-in for the grouping the listing now does properly, and
 *   two of the six links they carried pointed at ministries the listing was
 *   missing. Once the listing covers all eleven, the band has nothing left to
 *   do — and worse, it is a second, hand-maintained copy of the group names
 *   and their order.
 * - The verse and closing CTA bands keep their copy and get restyled in
 *   mcc-landing-bands.css.
 *
 * Idempotent: the tree is declared in full and replaces whatever is stored, so
 * re-running after a migration re-import restores the same result. Components
 * that already exist keep their stored inputs unless overridden below;
 * components that do not exist yet are created from `$create`.
 */

// Component versions, read from the canvas.component.* config entities. Canvas
// discovers both SDCs and views blocks automatically, so these exist as soon
// as the theme's component and the view are in place.
$versions = [];
$config_factory = \Drupal::configFactory();
foreach ([
  'sdc.mcc_theme.mcc-title-band',
  'block.views_block.mcc_ministry_groups-block_1',
  'block.system_breadcrumb_block',
] as $component_id) {
  $version = $config_factory->get('canvas.component.' . $component_id)->get('active_version');
  if (!$version) {
    print "aborting — canvas.component.$component_id does not exist. Run drush cr first.\n";
    return;
  }
  $versions[$component_id] = $version;
}

// Components this page needs that are not on it yet.
// uuid => [component_id, inputs]
$create = [
  'a1f0c4d2-3b7e-4a91-8c25-6de0f1b2a734' => [
    'sdc.mcc_theme.mcc-title-band',
    [
      'title' => 'Where people serve at MCC',
      'eyebrow' => 'Serve',
      'lede' => 'Every one of these is run by people in this congregation. God has given each of us different abilities and interests — if one of these fits how you’re gifted, say so on a Sunday morning.',
    ],
  ],
  'b2e1d5c3-4c8f-4b02-9d36-7ef1a2c3b845' => [
    'block.system_breadcrumb_block',
    ['label' => 'Breadcrumbs', 'label_display' => '0'],
  ],
  'c3d2e6b4-5d90-4c13-ae47-8f02b3d4c956' => [
    'block.views_block.mcc_ministry_groups-block_1',
    [
      'label' => '',
      'label_display' => '0',
      'views_label' => '',
      // Canvas validates this strictly: null or an integer, never the string
      // 'none'.
      'items_per_page' => NULL,
    ],
  ],
];

// The desired tree, depth first. Each entry is [uuid, slot, children].
// A NULL slot means top level.
$tree = [
  // Title band — bands 1 and 2, merged.
  ['a1f0c4d2-3b7e-4a91-8c25-6de0f1b2a734', NULL, [
    ['b2e1d5c3-4c8f-4b02-9d36-7ef1a2c3b845', 'breadcrumb', []],
    // The hero's existing "Find your place" button, moved into the band.
    ['570fb1f1-3668-4286-999b-6f0b9797333a', 'actions', []],
  ]],
  // The grouped listing.
  ['ebe50929-818b-4ec4-9769-2ee0d0d831b5', NULL, [
    ['c3d2e6b4-5d90-4c13-ae47-8f02b3d4c956', 'content', []],
  ]],
  // Ephesians 4:11-13.
  ['dc5c78f2-0c52-45f0-b5c6-f91974d320d6', NULL, [
    ['50b6934b-45b4-49ab-b56b-654f95660270', 'content', []],
  ]],
  // Closing CTA.
  ['b58f2169-cea5-496b-a747-c5720ce91bd2', NULL, [
    ['292425c4-634a-491c-a85e-5ad6a27a8754', 'content', [
      ['9af1c0b0-b70d-4f5a-84a3-ffbafff19a06', 'ctas', []],
      ['508b6cd8-2e84-4d6d-85c8-c10a6e727d68', 'ctas', []],
    ]],
  ]],
];

// Input overrides applied on top of what is stored, keyed by uuid.
$overrides = [
  // The button sat on a green hero and is now in a green band — same job,
  // same variant, and it only needs its size bumped to match the design's
  // large secondary button.
  '570fb1f1-3668-4286-999b-6f0b9797333a' => [
    'size' => 'large',
  ],
  // The listing band's own padding is now the group sections' business; the
  // section just supplies the surface and the container.
  'ebe50929-818b-4ec4-9769-2ee0d0d831b5' => [
    'section_id' => 'ministries-listing',
    'backgroundcolor' => 'white',
    'paddingtop' => 0,
    'paddingbottom' => 0,
  ],
  // The verse band inverts against the old page: the reference is the eyebrow
  // and the verse itself is the display type. That is a styling change in
  // mcc-landing-bands.css — the content is already in the right props.
  'dc5c78f2-0c52-45f0-b5c6-f91974d320d6' => [
    'section_id' => 'ministries-verse',
    'backgroundcolor' => 'white',
    'margintop' => 0,
    'marginbottom' => 0,
  ],
  'b58f2169-cea5-496b-a747-c5720ce91bd2' => [
    'section_id' => 'ministries-cta',
    'backgroundcolor' => 'dark-gray',
    'paddingtop' => 10,
    'paddingbottom' => 10,
  ],
  // On a terracotta band the standard secondary button would be
  // terracotta-on-terracotta, so the primary action is the oatmeal one. The
  // variant names are caresphere's; the colours come from the band's CSS.
  '9af1c0b0-b70d-4f5a-84a3-ffbafff19a06' => [
    'label' => 'Get involved',
    'href' => '/get-involved',
    'variant' => 'primary',
    'size' => 'large',
  ],
  '508b6cd8-2e84-4d6d-85c8-c10a6e727d68' => [
    'label' => 'Give',
    'href' => '/give',
    'variant' => 'secondary-white',
    'size' => 'large',
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
$added = [];

$walk = function (array $nodes, ?string $parent) use (&$walk, &$items, &$missing, &$added, $stored, $overrides, $create, $versions) {
  foreach ($nodes as [$uuid, $slot, $children]) {
    if (isset($stored[$uuid])) {
      $value = $stored[$uuid];
    }
    elseif (isset($create[$uuid])) {
      [$component_id, $inputs] = $create[$uuid];
      $value = [
        'uuid' => $uuid,
        'component_id' => $component_id,
        'component_version' => $versions[$component_id],
        'inputs' => json_encode($inputs),
        'label' => NULL,
      ];
      $added[] = $component_id;
    }
    else {
      $missing[] = $uuid;
      continue;
    }

    $value['parent_uuid'] = $parent;
    $value['slot'] = $slot;

    if (isset($overrides[$uuid])) {
      $inputs = json_decode($value['inputs'] ?: '{}', TRUE) ?: [];
      foreach ($overrides[$uuid] as $key => $new) {
        // A NULL override means "unset this input and let the SDC default win"
        // — except where NULL is itself the value Canvas wants, which is why
        // items_per_page is set in $create rather than here.
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
  print "aborting — these uuids are neither stored nor declared in \$create: " . implode(', ', $missing) . "\n";
  return;
}

$dropped = array_diff(array_keys($stored), array_column($items, 'uuid'));

$page->set('components', $items);
$page->save();

printf("rebuilt /ministries: %d components (was %d)\n", count($items), count($stored));
foreach ($added as $component_id) {
  print "  added   $component_id\n";
}
foreach ($dropped as $uuid) {
  printf("  dropped %s (%s)\n", $uuid, $stored[$uuid]['component_id']);
}
