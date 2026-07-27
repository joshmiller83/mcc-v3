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
 * - field_icon          — an SVG used on event detail pages. Deliberately not
 *   used on the calendar: at 7pt in print an icon is a smudge.
 *
 * The colours and shapes below are the five from the design handoff, plus one
 * for Equip (which the handoff's five-type palette doesn't cover). Everything
 * here is a starting point an editor can change in the UI afterwards.
 *
 * Safe to re-run. Run with: ddev drush php:script scripts/mcc_setup_category_styles.php
 */

use Drupal\Core\File\FileExists;
use Drupal\media\Entity\Media;

$file_repository = \Drupal::service('file.repository');
$file_system = \Drupal::service('file_system');
$term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

$dir = 'public://category-icons';
$file_system->prepareDirectory($dir, $file_system::CREATE_DIRECTORY | $file_system::MODIFY_PERMISSIONS);

/**
 * Wraps bare path data in a consistent 24x24 stroke-icon shell.
 *
 * The stroke is a literal black rather than `currentColor`: these are rendered
 * through CSS `mask-image`, which only reads the alpha channel, and an explicit
 * colour keeps that predictable regardless of where the file is loaded.
 */
$svg = function (string $paths): string {
  return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" '
    . 'fill="none" stroke="#000" stroke-width="1.75" stroke-linecap="round" '
    . 'stroke-linejoin="round">' . $paths . '</svg>';
};

// term name => [hex colour, marker shape, icon slug, icon path data]
$categories = [
  'Worship' => ['#263b29', 'circle', 'music-note',
    '<path d="M9 18V5l10-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="16" cy="16" r="3"/>',
  ],
  'Serve' => ['#924b29', 'triangle', 'serving-hands',
    '<path d="M12 12.5S8.2 10.1 8.2 7.3A2.6 2.6 0 0 1 12 5a2.6 2.6 0 0 1 3.8 2.3c0 2.8-3.8 5.2-3.8 5.2Z"/>'
    . '<path d="M4 15c1.8 3 4.7 4.8 8 4.8s6.2-1.8 8-4.8"/>',
  ],
  'Fellowship' => ['#674f43', 'square', 'two-people',
    '<circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0 1 12 0"/>'
    . '<path d="M16 5.5a3 3 0 0 1 0 5.8"/><path d="M21 20a6 6 0 0 0-4-5.6"/>',
  ],
  'Youth' => ['#932b27', 'ring', 'sprout',
    '<path d="M12 20v-7"/><path d="M12 13c0-3.3 2.7-6 6-6 0 3.3-2.7 6-6 6Z"/>'
    . '<path d="M12 15c0-2.8-2.2-5-5-5 0 2.8 2.2 5 5 5Z"/>',
  ],
  'Equip' => ['#3d5f5a', 'hexagon', 'open-book',
    '<path d="M12 7v13"/>'
    . '<path d="M12 7C10.5 5.5 8.4 4.7 6 4.7H3v12.6h3c2.4 0 4.5.8 6 2.3"/>'
    . '<path d="M12 7c1.5-1.5 3.6-2.3 6-2.3h3v12.6h-3c-2.4 0-4.5.8-6 2.3"/>',
  ],
  'Lead' => ['#6f5b16', 'diamond', 'compass',
    '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2.1 5.4-5.4 2.1 2.1-5.4 5.4-2.1Z"/>',
  ],
];

foreach ($categories as $name => [$color, $shape, $slug, $paths]) {
  $terms = $term_storage->loadByProperties([
    'vid' => 'mcc_mission_category',
    'name' => $name,
  ]);
  if (!$terms) {
    print "SKIP: no '$name' term found\n";
    continue;
  }
  $term = reset($terms);

  // Always rewrite the file so icon tweaks take effect on re-run; writeData()
  // reuses the existing File entity for a URI it already knows.
  $file = $file_repository->writeData(
    $svg($paths),
    $dir . '/' . $slug . '.svg',
    FileExists::Replace
  );

  // Reuse an existing icon media of the same name rather than piling up
  // duplicates every time this runs.
  $media_name = $name . ' category icon';
  $existing = \Drupal::entityTypeManager()->getStorage('media')
    ->loadByProperties(['bundle' => 'svg_image', 'name' => $media_name]);

  $media = $existing ? reset($existing) : Media::create([
    'bundle' => 'svg_image',
    'name' => $media_name,
    'status' => 1,
  ]);
  $media->set('field_media_svg_image', [
    'target_id' => $file->id(),
    'alt' => $name . ' icon',
  ]);
  $media->save();

  $term->set('field_category_color', ['color' => $color]);
  $term->set('field_marker_shape', $shape);
  $term->set('field_icon', ['target_id' => $media->id()]);
  $term->save();
  print sprintf("%-12s %s  %-9s icon=media/%d\n", $name, $color, $shape, $media->id());
}

print "Done.\n";
