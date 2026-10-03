<?php

/**
 * @file
 * Unpublishes the tip-created duplicate of the Women's Bible Study series.
 *
 * Run with: ddev drush php:script scripts/calendar-dedupe-womens-bible-study.php
 *
 * The series was entered twice: on mcc2026.dev on 2026-08-28 (node 1621, four
 * Wednesdays 09-09 → 09-30, no D7 counterpart) and in D7 on 2026-09-16 (D7 nid
 * 1625, twelve Wednesdays 09-09 → 11-25, with the "Song of Songs" picture), which
 * the 2026-10-01 sync imported as node 1625. With both published, every
 * September Wednesday showed it twice. This keeps 1625 — the longer, illustrated
 * series — unpublishes 1621, and 301s 1621's alias to the survivor.
 *
 * Idempotent, and it aborts without changing anything unless both nodes are what
 * it expects. That guard matters on an environment whose database predates the
 * 2026-10-01 sync: there 1625 does not exist and 1621 is the only copy, so
 * unpublishing it would remove the series outright. No `migrate:import` can
 * touch 1621 (it has no D7 row), so nothing undoes this run.
 */

use Drupal\pathauto\PathautoState;

// mcc_retire_stale_aliases(), shared with the other scripts that retire URLs.
require_once __DIR__ . '/ia-page-slugs.inc.php';

const DUPLICATE_NID = 1621;
const KEEP_NID = 1625;
const KEEP_D7_NID = 1625;
const TITLE = "Women's Bible Study";

$storage = \Drupal::entityTypeManager()->getStorage('node');
$database = \Drupal::database();

$duplicate = $storage->load(DUPLICATE_NID);
$keep = $storage->load(KEEP_NID);

foreach ([DUPLICATE_NID => $duplicate, KEEP_NID => $keep] as $nid => $node) {
  if (!$node) {
    print "Aborting: node $nid does not exist here. Has this environment received the 2026-10-01 sync?\n";
    exit(1);
  }
  if ($node->bundle() !== 'calendar_event' || trim($node->label()) !== TITLE) {
    printf("Aborting: node %d is a %s titled \"%s\", not a calendar_event titled \"%s\". Repurposed?\n",
      $nid, $node->bundle(), $node->label(), TITLE);
    exit(1);
  }
}

// The duplicate is the copy with no D7 row; the survivor is the D7 import.
if (!$database->schema()->tableExists('migrate_map_mcc_calendar_event')) {
  print "Aborting: no calendar migrate map here, so the import cannot be told from the tip-created copy.\n";
  exit(1);
}
$map = $database->select('migrate_map_mcc_calendar_event', 'm')
  ->fields('m', ['sourceid1', 'destid1'])
  ->condition('destid1', [DUPLICATE_NID, KEEP_NID], 'IN')
  ->execute()
  ->fetchAllKeyed(1, 0);
if (isset($map[DUPLICATE_NID])) {
  printf("Aborting: node %d maps to D7 nid %d, so it is not the tip-created copy.\n", DUPLICATE_NID, $map[DUPLICATE_NID]);
  exit(1);
}
if ((int) ($map[KEEP_NID] ?? 0) !== KEEP_D7_NID) {
  printf("Aborting: node %d is not the import of D7 nid %d.\n", KEEP_NID, KEEP_D7_NID);
  exit(1);
}
if (!$keep->isPublished()) {
  printf("Aborting: node %d, the copy to keep, is unpublished; retiring %d too would remove the series.\n", KEEP_NID, DUPLICATE_NID);
  exit(1);
}

if ($duplicate->isPublished()) {
  // Unpublish first. Pathauto regenerates an alias on every save, so the alias
  // has to be retired *after* this save or it gets handed straight back.
  $duplicate->setUnpublished();
  $duplicate->set('path', ['alias' => '', 'pathauto' => PathautoState::SKIP]);
  $duplicate->setNewRevision(TRUE);
  $duplicate->setRevisionCreationTime(\Drupal::time()->getRequestTime());
  $duplicate->setRevisionLogMessage(sprintf(
    'Unpublished as a duplicate of node %d, the same series imported from D7 (scripts/calendar-dedupe-womens-bible-study.php).',
    KEEP_NID
  ));
  $duplicate->save();
  printf("  %d \"%s\" unpublished; %d stays.\n", DUPLICATE_NID, TITLE, KEEP_NID);
}
else {
  printf("  %d already unpublished.\n", DUPLICATE_NID);
}

// Its old URL lands on the surviving series rather than on an unpublished page.
// No alias is kept, so every alias 1621 has is retired.
mcc_retire_stale_aliases(DUPLICATE_NID, '', KEEP_NID);

$remaining = $database->select('path_alias', 'a')
  ->condition('path', '/node/' . DUPLICATE_NID)
  ->countQuery()->execute()->fetchField();
printf("  aliases left on %d: %d\n", DUPLICATE_NID, $remaining);
