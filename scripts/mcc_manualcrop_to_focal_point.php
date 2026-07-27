<?php

/**
 * @file
 * Manually re-applies legacy Manual Crop rectangles as Focal Point crops.
 *
 * This normally runs on its own: mcc_migration subscribes to the migrate
 * POST_IMPORT event for mcc_files and converts the crop data every time the
 * file migration runs. Use this script only to re-run the conversion without a
 * full migration, or to inspect the report.
 *
 * Usage:
 *   ddev drush php:script scripts/mcc_manualcrop_to_focal_point.php
 *
 * @see \Drupal\mcc_migration\ManualCropToFocalPoint
 * @see \Drupal\mcc_migration\EventSubscriber\FocalPointMigrateSubscriber
 */

$report = \Drupal::service('mcc_migration.manualcrop_to_focal_point')->convert();

print "\n=== Manual Crop -> Focal Point ===\n";
printf("  applied:               %d\n", $report['converted']);
printf("  already current:       %d\n", $report['unchanged']);
printf("  duplicate crops removed: %d\n", $report['deduplicated']);
printf("  skipped, stray click:  %d\n", count($report['stray_click']));
printf("  skipped, no file:      %d\n", count($report['no_file']));
printf("  skipped, unreadable:   %d\n", count($report['unreadable']));

if ($report['stray_click']) {
  print "\nStray-click rectangles (left at default centre):\n";
  foreach ($report['stray_click'] as $line) {
    print "  $line\n";
  }
}
if ($report['unreadable']) {
  print "\nMissing or unreadable images:\n";
  foreach ($report['unreadable'] as $uri) {
    print "  $uri\n";
  }
}
if ($report['no_file']) {
  print "\nNo migrated file entity for legacy fids: " . implode(', ', $report['no_file']) . "\n";
}
print "\n";
