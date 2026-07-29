<?php

/**
 * @file
 * Seeds the icon media the About page's cards use.
 *
 * Run with:
 *   ddev drush php:script scripts/about-icons.php
 *
 * Same approach as scripts/mcc_setup_category_styles.php: hand-drawn 24x24
 * stroke icons (no brand icon set exists — see the About design handoff's
 * own note that Lucide there is a documented substitution), written to
 * public://about-icons and wrapped in an `svg_image` media entity, looked up
 * by name so re-running reuses them instead of piling up duplicates.
 *
 * Safe to re-run.
 */

use Drupal\Core\File\FileExists;
use Drupal\media\Entity\Media;

$file_repository = \Drupal::service('file.repository');
$file_system = \Drupal::service('file_system');

$dir = 'public://about-icons';
$file_system->prepareDirectory($dir, $file_system::CREATE_DIRECTORY | $file_system::MODIFY_PERMISSIONS);

$svg = function (string $paths): string {
  return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" '
    . 'fill="none" stroke="#000" stroke-width="1.75" stroke-linecap="round" '
    . 'stroke-linejoin="round">' . $paths . '</svg>';
};

// slug => [media name, path data]
$icons = [
  'users' => ['About icon — users',
    '<circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0 1 12 0"/>'
    . '<path d="M16 5.5a3 3 0 0 1 0 5.8"/><path d="M21 20a6 6 0 0 0-4-5.6"/>',
  ],
  'book-open' => ['About icon — open book',
    '<path d="M12 7v13"/>'
    . '<path d="M12 7C10.5 5.5 8.4 4.7 6 4.7H3v12.6h3c2.4 0 4.5.8 6 2.3"/>'
    . '<path d="M12 7c1.5-1.5 3.6-2.3 6-2.3h3v12.6h-3c-2.4 0-4.5.8-6 2.3"/>',
  ],
  'landmark' => ['About icon — landmark',
    '<path d="M3 21h18"/><path d="M5 21V10"/><path d="M9 21V10"/>'
    . '<path d="M15 21V10"/><path d="M19 21V10"/><path d="M2 10l10-6 10 6"/>',
  ],
  'user-round-check' => ['About icon — person with checkmark',
    '<circle cx="10" cy="8" r="4"/><path d="M4 21a6 6 0 0 1 10.5-4"/>'
    . '<path d="m16 19 2 2 4-4"/>',
  ],
  'droplet' => ['About icon — droplet',
    '<path d="M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11Z"/>',
  ],
  'heart' => ['About icon — heart',
    '<path d="M12 20s-7-4.35-9.5-8.5C1 8 2.5 4.5 6 4.5c2 0 3.5 1.5 4 2.5'
    . '.5-1 2-2.5 4-2.5 3.5 0 5 3.5 3.5 7C19 15.65 12 20 12 20Z"/>',
  ],
];

$media_ids = [];
foreach ($icons as $slug => [$name, $paths]) {
  $file = $file_repository->writeData($svg($paths), $dir . '/' . $slug . '.svg', FileExists::Replace);

  $existing = \Drupal::entityTypeManager()->getStorage('media')
    ->loadByProperties(['bundle' => 'svg_image', 'name' => $name]);

  $media = $existing ? reset($existing) : Media::create([
    'bundle' => 'svg_image',
    'name' => $name,
    'status' => 1,
  ]);
  $media->set('field_media_svg_image', [
    'target_id' => $file->id(),
    'alt' => '',
  ]);
  $media->save();
  $media_ids[$slug] = $media->id();
  printf("%s: media %d\n", $slug, $media->id());
}
