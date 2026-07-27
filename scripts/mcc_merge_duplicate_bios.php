<?php

/**
 * @file
 * Folds the duplicate bio records into one person each.
 *
 * Two people were entered twice on the legacy site — once per board they serve
 * on — instead of once with both roles. This merges each pair: the surviving
 * record gains the other's leadership group, anything it was missing is filled
 * in from the retired one, references follow the person, the old URL gets a
 * redirect, and the duplicate is unpublished rather than deleted.
 *
 * This normally runs on its own: mcc_migration subscribes to the migrate
 * POST_IMPORT event for mcc_bio and re-merges every time the bio migration
 * runs, because a re-import recreates and republishes the duplicates. Use this
 * script to run the merge without a full migration, or to inspect the report.
 *
 * Usage:
 *   ddev drush php:script scripts/mcc_merge_duplicate_bios.php
 *
 * @see \Drupal\mcc_migration\BioDuplicateMerger
 * @see \Drupal\mcc_migration\EventSubscriber\BioMergeMigrateSubscriber
 */

$report = \Drupal::service('mcc_migration.bio_duplicate_merger')->merge();

foreach (['merged', 'already_merged', 'skipped'] as $outcome) {
  if (!$report[$outcome]) {
    continue;
  }
  print strtoupper(str_replace('_', ' ', $outcome)) . ":\n";
  foreach ($report[$outcome] as $line) {
    print "  $line\n";
  }
}

if (!array_filter($report)) {
  print "Nothing to merge.\n";
}
