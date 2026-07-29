<?php

/**
 * @file
 * Rebuilds the /about Canvas page (canvas_page 4) to the design handoff.
 *
 * Run with:
 *   ddev drush php:script scripts/about-structure.php
 *
 * On the Pantheon sandbox:
 *   ddev exec terminus remote:drush mcc2026.dev -- php:script scripts/about-structure.php
 *
 * Why this exists
 * ---------------
 * The stored page is caresphere demo content that predates the design
 * handoff entirely — a random YouTube embed, a "Respect / Accountability /
 * Collaboration / Persistence" values grid, and four fabricated staff
 * testimonial cards (Sarah Chen, Marcus Williams…). None of it is About
 * copy, so this replaces the whole tree rather than patching it, following
 * the same pattern as scripts/homepage-structure.php: fixed UUIDs, media
 * looked up/created by name, Component::load() for component_version.
 *
 * Six bands, each a `section` owning its slot content (see AGENTS.md on why
 * every band is a section keyed by section_id, styled in
 * mcc-landing-bands.css, never via the backgroundcolor prop's bg-* classes):
 *
 *   about-hero      photo + scrim   breadcrumb, h1, lede
 *   about-vision    white           vision copy (left) / mission card (right)
 *   about-cards     card surface    "Four places to start" — the About IA
 *   about-glance    white           "At a glance" stats
 *   about-cta       green-900       closing CTA
 *
 * The vision copy is new prose written for this design (see the handoff's
 * own note) — the mission statement is the church's verbatim wording, but
 * the vision statement has no prior published form and needs elder sign-off
 * before this is treated as final.
 *
 * Idempotent: the tree is declared in full and replaces whatever is stored.
 */

use Drupal\canvas\Entity\Component;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;

const PAGE_ID = 4;

$file_system = \Drupal::service('file_system');
$theme_path = \Drupal::service('extension.list.theme')->getPath('mcc_theme');
$media_storage = \Drupal::entityTypeManager()->getStorage('media');

// ---------------------------------------------------------------------------
// The hero photo.
// ---------------------------------------------------------------------------
$hero_media_name = 'Placeholder — About hero: church building or grounds (wide)';
$existing = $media_storage->loadByProperties(['name' => $hero_media_name]);
if ($existing) {
  $hero_media_id = reset($existing)->id();
  print "hero media already exists (id $hero_media_id)\n";
}
else {
  $source = "$theme_path/images/placeholders/about-hero.jpg";
  $directory = 'public://placeholders';
  $file_system->prepareDirectory($directory, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY);
  $destination = "$directory/about-hero.jpg";
  $uri = $file_system->copy($source, $destination, \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE);
  $file = File::create(['uri' => $uri]);
  $file->setPermanent();
  $file->save();
  $media = Media::create([
    'bundle' => 'image',
    'name' => $hero_media_name,
    'field_media_image' => [
      'target_id' => $file->id(),
      'alt' => 'Placeholder: the church building or grounds, wide, subject in the upper half.',
    ],
    'status' => 1,
  ]);
  $media->save();
  $hero_media_id = $media->id();
  print "created hero media (id $hero_media_id)\n";
}

// Icon media, created by scripts/about-icons.php — look up by name.
$icon_ids = [];
foreach ([
  'users' => 'About icon — users',
  'book-open' => 'About icon — open book',
  'landmark' => 'About icon — landmark',
  'user-round-check' => 'About icon — person with checkmark',
  'droplet' => 'About icon — droplet',
  'heart' => 'About icon — heart',
] as $slug => $name) {
  $found = $media_storage->loadByProperties(['name' => $name]);
  if (!$found) {
    print "missing icon media '$name' — run scripts/about-icons.php first\n";
    return;
  }
  $icon_ids[$slug] = reset($found)->id();
}

// ---------------------------------------------------------------------------
// The tree.
// ---------------------------------------------------------------------------
$SEC_HERO = 'ab000001-0000-4000-8000-000000000001';
$HERO_BREADCRUMB = 'ab000001-0000-4000-8000-000000000002';
$HERO_INTRO = 'ab000001-0000-4000-8000-000000000003';

$SEC_VISION = 'ab000002-0000-4000-8000-000000000001';
$VISION_GRID = 'ab000002-0000-4000-8000-000000000002';
$VISION_INTRO = 'ab000002-0000-4000-8000-000000000003';
$MISSION_INTRO = 'ab000002-0000-4000-8000-000000000004';
$MISSION_ROW_1 = 'ab000002-0000-4000-8000-000000000005';
$MISSION_ROW_2 = 'ab000002-0000-4000-8000-000000000006';
$MISSION_ROW_3 = 'ab000002-0000-4000-8000-000000000007';

$SEC_CARDS = 'ab000003-0000-4000-8000-000000000001';
$CARDS_INTRO = 'ab000003-0000-4000-8000-000000000002';
$CARDS_GRID = 'ab000003-0000-4000-8000-000000000003';
$CARD_WHO = 'ab000003-0000-4000-8000-000000000004';
$CARD_BELIEFS = 'ab000003-0000-4000-8000-000000000005';
$CARD_HISTORY = 'ab000003-0000-4000-8000-000000000006';
$CARD_LEADERSHIP = 'ab000003-0000-4000-8000-000000000007';

$SEC_GLANCE = 'ab000004-0000-4000-8000-000000000001';
$GLANCE_INTRO = 'ab000004-0000-4000-8000-000000000002';
$GLANCE_GRID = 'ab000004-0000-4000-8000-000000000003';
$STAT_1840 = 'ab000004-0000-4000-8000-000000000004';
$STAT_WORSHIP = 'ab000004-0000-4000-8000-000000000005';
$STAT_BUILDINGS = 'ab000004-0000-4000-8000-000000000006';
$STAT_DEBT = 'ab000004-0000-4000-8000-000000000007';

$SEC_CTA = 'ab000005-0000-4000-8000-000000000001';
$CTA_INTRO = 'ab000005-0000-4000-8000-000000000002';
$CTA_BUTTON_1 = 'ab000005-0000-4000-8000-000000000003';
$CTA_BUTTON_2 = 'ab000005-0000-4000-8000-000000000004';

$tree = [
  [$SEC_HERO, NULL, [
    [$HERO_BREADCRUMB, 'content', []],
    [$HERO_INTRO, 'content', []],
  ]],
  [$SEC_VISION, NULL, [
    [$VISION_GRID, 'content', [
      [$VISION_INTRO, 'slot_1', []],
      [$MISSION_INTRO, 'slot_2', []],
      [$MISSION_ROW_1, 'slot_2', []],
      [$MISSION_ROW_2, 'slot_2', []],
      [$MISSION_ROW_3, 'slot_2', []],
    ]],
  ]],
  [$SEC_CARDS, NULL, [
    [$CARDS_INTRO, 'content', []],
    [$CARDS_GRID, 'content', [
      [$CARD_WHO, 'slot_1', []],
      [$CARD_BELIEFS, 'slot_1', []],
      [$CARD_HISTORY, 'slot_1', []],
      [$CARD_LEADERSHIP, 'slot_1', []],
    ]],
  ]],
  [$SEC_GLANCE, NULL, [
    [$GLANCE_INTRO, 'content', []],
    [$GLANCE_GRID, 'content', [
      [$STAT_1840, 'slot_1', []],
      [$STAT_WORSHIP, 'slot_1', []],
      [$STAT_BUILDINGS, 'slot_1', []],
      [$STAT_DEBT, 'slot_1', []],
    ]],
  ]],
  [$SEC_CTA, NULL, [
    [$CTA_INTRO, 'content', [
      [$CTA_BUTTON_1, 'ctas', []],
      [$CTA_BUTTON_2, 'ctas', []],
    ]],
  ]],
];

$band = fn(string $id, string $bg, string $align = 'center', int $pt = 10, int $pb = 10, array $extra = []) => [
  'component_id' => 'sdc.caresphere_theme.section',
  'inputs' => [
    'section_id' => $id,
    'backgroundcolor' => $bg,
    'alignment' => $align,
    'paddingtop' => $pt,
    'paddingbottom' => $pb,
    'margintop' => 0,
    'marginbottom' => 0,
    'use_custom_container' => FALSE,
  ] + $extra,
];

$icon = fn(string $slug, string $text, string $description) => [
  'component_id' => 'sdc.caresphere_theme.card-icon',
  'inputs' => [
    'media' => ['target_id' => (string) $icon_ids[$slug]],
    'icon_size' => 'small',
    'icon_align' => 'left',
    'text_align' => 'left',
    'text' => $text,
    'description' => ['value' => "<p>$description</p>", 'format' => 'canvas_html_block'],
    'border_radius' => 'medium',
  ],
];

$place_card = fn(string $slug, string $text, string $description, string $url, string $cta) => [
  'component_id' => 'sdc.caresphere_theme.card-icon',
  'inputs' => [
    'media' => ['target_id' => (string) $icon_ids[$slug]],
    'icon_size' => 'large',
    'icon_align' => 'left',
    'text_align' => 'left',
    'border_radius' => 'medium',
    'backgroundcolor' => 'white',
    'text' => $text,
    'description' => ['value' => "<p>$description</p>", 'format' => 'canvas_html_block'],
    'cta_label' => $cta,
    'cta_url' => $url,
    'cta_variant' => 'link',
  ],
];

$stat = fn(string $value, string $label) => [
  'component_id' => 'sdc.caresphere_theme.stat-card',
  'inputs' => [
    'value' => $value,
    'label' => $label,
  ],
];

$created = [
  $SEC_HERO => $band('about-hero', 'black', 'left', 10, 10, array_filter([
    'background_image' => ['target_id' => (string) $hero_media_id],
  ])),
  $HERO_BREADCRUMB => [
    'component_id' => 'block.system_breadcrumb_block',
    'inputs' => ['label' => 'Breadcrumbs', 'label_display' => '0'],
  ],
  $HERO_INTRO => [
    'component_id' => 'sdc.caresphere_theme.section-intro',
    'inputs' => [
      'heading' => ['value' => 'Deep roots in Boone County.', 'format' => 'canvas_html_inline'],
      'description' => "Mechanicsburg Christian Church has met on this ground since 1840 — a country congregation that still measures itself by whether it loves people well.",
      'textcolor' => 'light',
      'heading_level' => 1,
    ],
  ],

  $SEC_VISION => $band('about-vision', 'white', 'left'),
  $VISION_GRID => [
    'component_id' => 'sdc.caresphere_theme.section-grid',
    'inputs' => [
      'grid_layout' => '2_column_50_50',
      'grid_gap' => 16,
      'alignment' => 'start',
      'column_1_layout' => 'full',
      'column_2_layout' => 'full',
    ],
  ],
  $VISION_INTRO => [
    'component_id' => 'sdc.caresphere_theme.section-intro',
    'inputs' => [
      'tagline' => 'Our vision',
      'heading' => ['value' => 'A spiritual home for rural Boone County.', 'format' => 'canvas_html_inline'],
      'description' => "We exist for the county around us, not just the congregation inside our doors — a place where farm families, newcomers to Kirklin and everyone between can find the same welcome. That has held since a small group of men cut logs for a church in 1840, and it still shapes what we build and who we serve.\n\nWe hold to the old Restoration plea: no creed but Christ, no law but love, no book but the Bible. It is why we would rather agree on the essentials than divide over opinions.",
      'heading_level' => 2,
    ],
  ],
  $MISSION_INTRO => [
    'component_id' => 'sdc.caresphere_theme.section-intro',
    'inputs' => [
      'tagline' => 'Our mission',
      'heading' => [
        'value' => '<em>We walk by faith, embracing all that God desires, reaching the world, through Jesus Christ!</em>',
        'format' => 'canvas_html_inline',
      ],
      'heading_level' => 3,
    ],
  ],
  $MISSION_ROW_1 => $icon('book-open', 'Scripture is our guide', 'The Bible is our final rule of faith and practice — no creed but Christ.'),
  $MISSION_ROW_2 => $icon('droplet', 'Baptism marks new life', 'We baptize believers by immersion, following Christ\'s own example.'),
  $MISSION_ROW_3 => $icon('heart', 'We gather at the table', 'Communion is part of worshiping together each week we meet.'),

  $SEC_CARDS => $band('about-cards', 'gray-light', 'center'),
  $CARDS_INTRO => [
    'component_id' => 'sdc.caresphere_theme.section-intro',
    'inputs' => [
      'tagline' => 'Get to know us',
      'heading' => ['value' => 'Four places to start', 'format' => 'canvas_html_inline'],
      'description' => 'Wherever you are in getting to know this church, one of these is the next step.',
      'heading_level' => 2,
    ],
  ],
  $CARDS_GRID => [
    'component_id' => 'sdc.caresphere_theme.section-grid',
    'inputs' => [
      'grid_layout' => '1_column',
      'grid_gap' => 6,
      'column_1_layout' => 'stacked',
    ],
  ],
  $CARD_WHO => $place_card('users', 'Who we are', 'Meet the congregation and see what a Sunday here looks like.', '/about/who-we-are', 'Meet the congregation'),
  $CARD_BELIEFS => $place_card('book-open', 'Our beliefs', 'What Scripture, salvation, baptism and the church mean to us.', '/about/beliefs', 'Read what we believe'),
  $CARD_HISTORY => $place_card('landmark', 'Our history', 'Five buildings, one fire, and 185 years on the same ground.', '/about/history', 'Walk through our history'),
  $CARD_LEADERSHIP => $place_card('user-round-check', 'Our leadership', 'The elders, deacons and ministry leaders who serve this church.', '/about/leadership', 'See who serves'),

  $SEC_GLANCE => $band('about-glance', 'white', 'left'),
  $GLANCE_INTRO => [
    'component_id' => 'sdc.caresphere_theme.section-intro',
    'inputs' => [
      'tagline' => 'At a glance',
      'heading' => ['value' => 'Nearly two centuries on one plot of ground', 'format' => 'canvas_html_inline'],
      'heading_level' => 2,
    ],
  ],
  $GLANCE_GRID => [
    'component_id' => 'sdc.caresphere_theme.section-grid',
    'inputs' => [
      'grid_layout' => '1_column',
      'grid_gap' => 6,
      'column_1_layout' => 'stacked',
    ],
  ],
  $STAT_1840 => $stat('1840', 'Founded on woodland deeded for perpetual church use'),
  $STAT_WORSHIP => $stat('80–100', 'Neighbors in worship on a typical Sunday'),
  $STAT_BUILDINGS => $stat('5', 'Buildings on this ground, log cabin to worship center'),
  $STAT_DEBT => $stat('$0', 'Debt carried since March 2020'),

  $SEC_CTA => $band('about-cta', 'black'),
  $CTA_INTRO => [
    'component_id' => 'sdc.caresphere_theme.section-intro',
    'inputs' => [
      'heading' => ['value' => 'Come see for yourself.', 'format' => 'canvas_html_inline'],
      'description' => 'There is a seat for you this Sunday, and a place to serve during the week.',
      'textcolor' => 'light',
      'heading_level' => 2,
    ],
  ],
  $CTA_BUTTON_1 => [
    'component_id' => 'sdc.caresphere_theme.button',
    'inputs' => [
      'href' => '/get-involved',
      'label' => 'Plan your visit',
      'variant' => 'secondary-white',
      'size' => 'large',
    ],
  ],
  $CTA_BUTTON_2 => [
    'component_id' => 'sdc.caresphere_theme.button',
    'inputs' => [
      'href' => '/sermons',
      'label' => 'Watch a sermon',
      'variant' => 'link',
      'size' => 'large',
    ],
  ],
];

// ---------------------------------------------------------------------------
// Apply.
// ---------------------------------------------------------------------------
$page = \Drupal::entityTypeManager()->getStorage('canvas_page')->load(PAGE_ID);
if (!$page) {
  print "no canvas_page " . PAGE_ID . " (/about)\n";
  return;
}

$items = [];
$missing = [];

$walk = function (array $nodes, ?string $parent) use (&$walk, &$items, &$missing, $created) {
  foreach ($nodes as [$uuid, $slot, $children]) {
    $spec = $created[$uuid] ?? NULL;
    if (!$spec) {
      $missing[] = $uuid;
      continue;
    }
    $component = Component::load($spec['component_id']);
    if (!$component) {
      $missing[] = $spec['component_id'] . ' (component config)';
      continue;
    }
    $items[] = [
      'uuid' => $uuid,
      'parent_uuid' => $parent,
      'slot' => $slot,
      'component_id' => $spec['component_id'],
      'component_version' => $component->getActiveVersion(),
      'inputs' => json_encode($spec['inputs']),
      'label' => NULL,
    ];
    if ($children) {
      $walk($children, $uuid);
    }
  }
};
$walk($tree, NULL);

if ($missing) {
  print "aborting — not found: " . implode(', ', $missing) . "\n";
  return;
}

$page->set('components', $items);
$page->save();

printf("rebuilt /about: %d components in 5 bands\n", count($items));
