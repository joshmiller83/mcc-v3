<?php

/**
 * @file
 * Seeds the Mission Category terms with their calendar styling and an icon.
 *
 * The calendar draws every category from three fields on its taxonomy term:
 *
 * - field_category_color — the colour, as a plain hex value. The calendar emits
 *   it as a CSS custom property and derives the pale chip/band background from
 *   it with color-mix(), so a new colour never needs a stylesheet change.
 * - field_marker_shape  — the small CSS-drawn shape beside each event. The
 *   office photocopies the printed calendar in black and white, where the
 *   shape is the only thing left distinguishing the categories.
 * - field_icon          — an icon shown on event detail pages. Deliberately not
 *   used on the calendar: at 7pt in print an icon is a smudge. This is core's
 *   Icon API field over the Lucide pack — the same field type and the same
 *   pack the ministries use, so there is one way to choose an icon on this
 *   site and one place icons come from. It replaced a per-category SVG media
 *   entity that an editor had to draw and upload before they could use it.
 *
 * The colours and shapes below are the five from the design handoff, plus one
 * for Equip (which the handoff's five-type palette doesn't cover). Everything
 * here is a starting point an editor can change in the UI afterwards.
 *
 * Safe to re-run. Run with: ddev drush php:script scripts/mcc_setup_category_styles.php
 */

$term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

// term name => [hex colour, marker shape, Lucide icon]
$categories = [
  'Worship' => ['#263b29', 'circle', 'lucide:music'],
  'Serve' => ['#924b29', 'triangle', 'lucide:hand-heart'],
  'Fellowship' => ['#674f43', 'square', 'lucide:users'],
  'Youth' => ['#932b27', 'ring', 'lucide:sprout'],
  'Equip' => ['#3d5f5a', 'hexagon', 'lucide:book-open'],
  'Lead' => ['#6f5b16', 'diamond', 'lucide:compass'],
];

foreach ($categories as $name => [$color, $shape, $icon]) {
  $terms = $term_storage->loadByProperties([
    'vid' => 'mcc_mission_category',
    'name' => $name,
  ]);
  if (!$terms) {
    print "SKIP: no '$name' term found\n";
    continue;
  }
  $term = reset($terms);

  $term->set('field_category_color', $color);
  $term->set('field_marker_shape', $shape);
  $term->set('field_icon', ['target_id' => $icon]);
  $term->save();

  printf("%-12s %-8s %-8s %s\n", $name, $color, $shape, $icon);
}

print "Done.\n";
