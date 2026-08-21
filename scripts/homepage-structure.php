<?php

/**
 * @file
 * Rebuilds the front page (Canvas page 9) into nested section bands, and wires
 * the four design photo slots to real placeholder media.
 *
 * Run with:
 *   ddev drush php:script scripts/homepage-structure.php
 *
 * On the Pantheon sandbox (content lives in the DB, not in git — this script is
 * how a content change travels with a commit):
 *   ddev exec terminus remote:drush mcc2026.dev -- php:script scripts/homepage-structure.php
 *
 * Why this exists
 * ---------------
 * The homepage was the only Canvas page on the site with no `section`
 * components at all — every band was a bare top-level `section-intro` /
 * `section-grid` sibling. /get-involved, /im-new and /ministries all nest their
 * components inside a section that carries the band's id, background and
 * padding (see mcc-landing-bands.css, keyed on those ids). The homepage had
 * none of that, so it had no band backgrounds and no vertical rhythm.
 *
 * It was not obvious because global-overrides.css *appeared* to compensate,
 * with rules like `div[data-component-id="caresphere_theme:section-intro"]`.
 * Those never matched: of the components this page uses, only section,
 * section-grid and hero-card print `{{ attributes }}` in their Twig, so only
 * they carry a `data-component-id`. Every rule keyed to a button, section-intro
 * or stat-card selector was dead CSS — including the one meant to paint the
 * closing "We'd love to meet you" band green. Those rules are removed in
 * global-overrides.css; the bands are done here, the way the other three pages
 * already do it.
 *
 * The photo slots
 * ---------------
 * The design handoff asks for one reusable image block used four times. That
 * block already exists — caresphere's `image` SDC, which is what Canvas offers
 * an editor under "Base > Image" — so this uses it rather than adding a
 * bespoke component. mcc_theme's own `mcc-photo-placeholder` SDC (a grey
 * dashed box with a label) is retired by this script: it was a stand-in that
 * could never hold a real photo, so an editor had to swap the component to
 * publish one. Now the slot is a real image block that already has an image in
 * it, and publishing a photo is "replace this image" — no component surgery.
 *
 * The placeholder JPGs ship in the theme (images/placeholders/) so they deploy
 * with code; this script copies them into the public files directory and mints
 * one media entity each, keyed by name so re-running reuses them.
 *
 * The hero and the closing photo do not use the image block:
 *   - The hero photo is the *section's* `background_image`, because `section`
 *     already renders a background layer plus an overlay we can style as the
 *     design's green scrim. (caresphere's `hero-card` declares a `media` prop
 *     and ships .hero-card__bg/__image/__overlay CSS, but its Twig never
 *     renders any of it — the prop is inert. Worth reporting upstream.)
 *   - The closing photo is an image block in its own zero-padding band.
 *
 * Idempotent: the tree is declared in full and replaces whatever is stored, and
 * the media lookup is by name, so re-running after a migration re-import
 * restores the same result.
 */

use Drupal\canvas\Entity\Component;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;

const PAGE_ID = 9;

/**
 * The placeholder images, and the photo each one is standing in for.
 *
 * The `alt` text is deliberately a description of the photo we still need
 * rather than a description of the grey box: it is what a screen-reader user
 * and an editor both see, and it keeps the "what goes here" brief attached to
 * the slot instead of living only in a handoff document.
 */
$placeholders = [
  'hero-photo' => [
    'name' => 'Placeholder — hero: congregation or sanctuary (wide)',
    'alt' => 'Placeholder: a wide photo of the congregation or sanctuary, with the subject right of centre.',
  ],
  'stat-photo-1' => [
    'name' => 'Placeholder — impact tile 1: the sanctuary',
    'alt' => 'Placeholder: the sanctuary — stone wall, wooden cross, congregation quilts on the back wall.',
  ],
  'stat-photo-2' => [
    'name' => 'Placeholder — impact tile 2: Sunday worship',
    'alt' => 'Placeholder: Sunday worship in progress, candid, natural indoor light.',
  ],
  'cta-photo' => [
    'name' => 'Placeholder — closing band: the congregation together',
    'alt' => 'Placeholder: the congregation together — a group shot or fellowship moment.',
  ],
];

$file_system = \Drupal::service('file_system');
$theme_path = \Drupal::service('extension.list.theme')->getPath('mcc_theme');
$media_storage = \Drupal::entityTypeManager()->getStorage('media');

$media_ids = [];
foreach ($placeholders as $slug => $info) {
  // Look up by name so a re-run reuses the media instead of stacking copies.
  $existing = $media_storage->loadByProperties(['name' => $info['name']]);
  if ($existing) {
    $media = reset($existing);
    $media_ids[$slug] = $media->id();
    printf("  media '%s' already exists (id %d)\n", $slug, $media->id());
    continue;
  }

  $source = sprintf('%s/images/placeholders/%s.jpg', $theme_path, $slug);
  if (!file_exists($source)) {
    printf("  ! missing source image %s — skipping\n", $source);
    continue;
  }

  $directory = 'public://placeholders';
  $file_system->prepareDirectory($directory, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY);
  $destination = sprintf('%s/%s.jpg', $directory, $slug);
  $uri = $file_system->copy($source, $destination, \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE);

  $file = File::create(['uri' => $uri]);
  $file->setPermanent();
  $file->save();

  $media = Media::create([
    'bundle' => 'image',
    'name' => $info['name'],
    'field_media_image' => [
      'target_id' => $file->id(),
      'alt' => $info['alt'],
    ],
    'status' => 1,
  ]);
  $media->save();
  $media_ids[$slug] = $media->id();
  printf("  created media '%s' (id %d)\n", $slug, $media->id());
}

// ---------------------------------------------------------------------------
// The tree.
// ---------------------------------------------------------------------------
//
// Existing component uuids are reused wherever the component survives, so the
// stored inputs (all the page's copy) carry over untouched. The six band
// sections and the three image blocks are new, with fixed uuids so re-running
// is stable.

// Existing.
$HERO_CARD      = 'b74fd94f-7b81-4f51-8ebd-d6f5f87f4e7a';
$HERO_CTA_1     = 'feaa47b4-771e-4107-955d-0e123ad99da7';
$HERO_CTA_2     = '3dc87473-a1ac-4cfc-ad04-d3f6da1a6bec';
$IMPACT_INTRO   = 'f65717f6-79e9-4a0e-8cf2-978cd0879c05';
$IMPACT_GRID    = 'ded80401-b1ff-4365-b33f-db65418065e8';
$STAT_YEARS     = 'd54e0de4-65e5-4150-bcbc-4df4779e7566';
$IMPACT_TEXT    = 'ba8f5403-67b9-47d6-90f2-7095703b4591';
$STAT_WORSHIP   = '6fd8abb1-35f0-45ca-a99d-a294c2c68cb5';
$STAT_MEALS     = '4632dbed-2e50-4f87-a849-d2911b80bd5f';
$MIN_INTRO      = 'e8686b86-68c5-40f3-ad91-8c8fef167b98';
$MIN_GRID       = 'b7a194b1-3124-48b0-83f4-004dd5ed66ac';
$MIN_CARD_1     = 'ad170998-cc39-49fd-aca8-629e37d38ae3';
$MIN_CARD_2     = 'a81cfe8a-f9fd-402f-b38b-a675e78b2f26';
$MIN_CARD_3     = 'cfeee694-5db6-4f0a-90de-03604400b51d';
$MIN_BUTTON     = 'a7610dbc-a02f-470f-bdff-b9038cf4a506';
$EVENTS_INTRO   = 'a919c773-d3b4-412f-916e-f1750efa56c4';
$EVENTS_BLOCK   = '0fea6d1c-6166-4ab1-b0a8-79a85a31459b';
$EVENTS_BUTTON  = '3129215f-6c91-4219-9c19-abe0c8e5ba88';
$MISSION_QUOTE  = '0e1350ba-8509-487a-8915-40119bfd1633';
$CTA_INTRO      = '2d5c9689-59d5-4d42-a796-6161b68f8ebc';
$CTA_BUTTON_1   = '7a459a36-956b-4fd2-9f54-3e03e81c7d3a';
$CTA_BUTTON_2   = '44bfe04d-ac54-4f74-962b-2626b20c7099';

// Retired: the two mcc-photo-placeholder components, replaced by image blocks.
// 9e6a4d13-2225-4787-8e01-3fbe5fffd9f7, e240a555-bb05-4c0b-b581-6f1db5416516

// New — bands.
$SEC_HERO       = 'aa000001-0000-4000-8000-000000000001';
$SEC_IMPACT     = 'aa000002-0000-4000-8000-000000000002';
$SEC_MINISTRIES = 'aa000003-0000-4000-8000-000000000003';
$SEC_EVENTS     = 'aa000004-0000-4000-8000-000000000004';
$SEC_MISSION    = 'aa000005-0000-4000-8000-000000000005';
$SEC_CTA        = 'aa000006-0000-4000-8000-000000000006';
$SEC_PHOTO      = 'aa000007-0000-4000-8000-000000000007';
// New — image blocks.
$IMG_STAT_1     = 'bb000001-0000-4000-8000-000000000001';
$IMG_STAT_2     = 'bb000002-0000-4000-8000-000000000002';
$IMG_CLOSING    = 'bb000003-0000-4000-8000-000000000003';

$tree = [
  // 1. Hero — photo behind, green scrim over, content left.
  [$SEC_HERO, NULL, [
    [$HERO_CARD, 'content', [
      [$HERO_CTA_1, 'ctas', []],
      [$HERO_CTA_2, 'ctas', []],
    ]],
  ]],

  // 2. Impact mosaic. Column-major: the grid stacks each slot, so slot_1 is
  // the left column, not the top row.
  [$SEC_IMPACT, NULL, [
    [$IMPACT_INTRO, 'content', []],
    [$IMPACT_GRID, 'content', [
      [$STAT_YEARS, 'slot_1', []],
      [$IMPACT_TEXT, 'slot_1', []],
      [$IMG_STAT_1, 'slot_2', []],
      [$STAT_WORSHIP, 'slot_2', []],
      [$STAT_MEALS, 'slot_3', []],
      [$IMG_STAT_2, 'slot_3', []],
    ]],
  ]],

  // 3. Ministries.
  [$SEC_MINISTRIES, NULL, [
    [$MIN_INTRO, 'content', []],
    [$MIN_GRID, 'content', [
      [$MIN_CARD_1, 'slot_1', []],
      [$MIN_CARD_2, 'slot_2', []],
      [$MIN_CARD_3, 'slot_3', []],
    ]],
    [$MIN_BUTTON, 'content', []],
  ]],

  // 4. This week at MCC.
  [$SEC_EVENTS, NULL, [
    [$EVENTS_INTRO, 'content', []],
    [$EVENTS_BLOCK, 'content', []],
    [$EVENTS_BUTTON, 'content', []],
  ]],

  // 5. Mission statement.
  [$SEC_MISSION, NULL, [
    [$MISSION_QUOTE, 'content', []],
  ]],

  // 6. Closing CTA.
  [$SEC_CTA, NULL, [
    [$CTA_INTRO, 'content', [
      [$CTA_BUTTON_1, 'ctas', []],
      [$CTA_BUTTON_2, 'ctas', []],
    ]],
  ]],

  // 7. Full-bleed closing photo.
  [$SEC_PHOTO, NULL, [
    [$IMG_CLOSING, 'content', []],
  ]],
];

// Components this script creates rather than finds.
// `paddingtop`/`paddingbottom` are multiples of 8px doubled into rem by
// section.twig (10 => 5rem), matching the other three landing pages.
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

$image_block = fn(?int $media_id, string $size, int $radius = 10) => [
  'component_id' => 'sdc.caresphere_theme.image',
  'inputs' => array_filter([
    'media' => $media_id ? ['target_id' => (string) $media_id] : NULL,
    'size' => $size,
    'border_radius' => $radius,
  ]),
];

$created = [
  // The hero's photo is the band's background image; the overlay above it is
  // restyled into the design's green scrim in mcc-landing-bands.css.
  $SEC_HERO => $band('home-hero', 'black', 'left', 0, 0, array_filter([
    'background_image' => isset($media_ids['hero-photo'])
      ? ['target_id' => (string) $media_ids['hero-photo']]
      : NULL,
  ])),
  $SEC_IMPACT => $band('home-impact', 'white'),
  $SEC_MINISTRIES => $band('home-ministries', 'gray-light'),
  $SEC_EVENTS => $band('home-events', 'white'),
  $SEC_MISSION => $band('home-mission', 'dark-gray', 'center', 8, 8),
  $SEC_CTA => $band('home-cta', 'black'),
  // Full bleed: no padding, the photo is the band.
  $SEC_PHOTO => $band('home-closing-photo', 'white', 'center', 0, 0),

  $IMG_STAT_1 => $image_block($media_ids['stat-photo-1'] ?? NULL, '1:1'),
  $IMG_STAT_2 => $image_block($media_ids['stat-photo-2'] ?? NULL, '1:1'),
  $IMG_CLOSING => $image_block($media_ids['cta-photo'] ?? NULL, '2:1', 0),
];

// Input overrides on components that already exist.
$overrides = [
  // "See all ministries" was stored with disabled:true, and button.twig nulls
  // the href when disabled — so the section's main CTA rendered as an inert
  // <button disabled> that could not be clicked or focused. It is a link.
  $MIN_BUTTON => [
    'disabled' => FALSE,
  ],
  // Both hero CTAs were stored disabled:true, so they rendered as inert,
  // half-opacity <button disabled> elements even though each already stores a
  // valid href (/sermons and /im-new) — same defect as $MIN_BUTTON above.
  $HERO_CTA_1 => [
    'disabled' => FALSE,
  ],
  $HERO_CTA_2 => [
    'disabled' => FALSE,
  ],
  // "Plan your visit" pointed at /i-am-new-here, which 404s. The page is
  // /im-new (see AGENTS.md — I'm New and Get Involved are separate pages).
  $CTA_BUTTON_1 => [
    'href' => '/im-new',
  ],
  // "Give" sits on the dark green closing band, so it needs the light outline
  // variant; secondary-dark paints near-black text on near-black green.
  $CTA_BUTTON_2 => [
    'variant' => 'secondary-white',
  ],
  // The two stat cards that share a column with a photo should fill it, so the
  // mosaic's rows line up instead of leaving ragged gaps.
  $STAT_YEARS => ['full_height' => TRUE],
  $STAT_WORSHIP => ['full_height' => TRUE],
  $STAT_MEALS => ['full_height' => TRUE],
];

// ---------------------------------------------------------------------------
// Apply.
// ---------------------------------------------------------------------------

$page = \Drupal::entityTypeManager()->getStorage('canvas_page')->load(PAGE_ID);
if (!$page) {
  print "no canvas_page " . PAGE_ID . " (front page)\n";
  return;
}

$stored = [];
foreach ($page->get('components') as $item) {
  $value = $item->getValue();
  $stored[$value['uuid']] = $value;
}

// caresphere's hero-card `media` prop was inert when this script was written
// (its Twig rendered none of it), so the real hero photo an editor attached to
// the card never conflicted with the band's placeholder background. The base
// theme has since started rendering it, which double-exposed the hero: the
// real photo painted inside the card, on top of the scrimmed placeholder
// band. The band's background_image is where the design wants the photo (see
// the header comment), so promote the card's photo to the band and clear it
// from the card. Idempotent: once promoted, the card stores no media and this
// block is a no-op.
if (isset($stored[$HERO_CARD])) {
  $card_inputs = json_decode($stored[$HERO_CARD]['inputs'] ?: '{}', TRUE) ?: [];
  if (!empty($card_inputs['media']['target_id'])) {
    $overrides[$SEC_HERO]['background_image'] = ['target_id' => (string) $card_inputs['media']['target_id']];
    $overrides[$HERO_CARD]['media'] = NULL;
  }
}

$items = [];
$missing = [];

$walk = function (array $nodes, ?string $parent) use (&$walk, &$items, &$missing, $stored, $overrides, $created) {
  foreach ($nodes as [$uuid, $slot, $children]) {
    if (isset($stored[$uuid])) {
      $value = $stored[$uuid];
    }
    elseif (isset($created[$uuid])) {
      $spec = $created[$uuid];
      $component = Component::load($spec['component_id']);
      if (!$component) {
        $missing[] = $spec['component_id'] . ' (component config)';
        continue;
      }
      $value = [
        'uuid' => $uuid,
        'component_id' => $spec['component_id'],
        'component_version' => $component->getActiveVersion(),
        'inputs' => json_encode($spec['inputs']),
        'label' => NULL,
      ];
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
        // A NULL override means "unset and let the SDC default win".
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
  print "aborting — not found: " . implode(', ', $missing) . "\n";
  return;
}

$dropped = array_diff(array_keys($stored), array_column($items, 'uuid'));

$page->set('components', $items);
$page->save();

printf(
  "rebuilt the front page: %d components in 7 nested bands (was %d, %d of them top level)\n",
  count($items),
  count($stored),
  count(array_filter($stored, fn($v) => empty($v['parent_uuid'])))
);
foreach ($dropped as $uuid) {
  printf("  dropped %s (%s)\n", $uuid, $stored[$uuid]['component_id']);
}
