<?php

/**
 * @file
 * Unpublishes the nodes whose D7 source was deleted on mcc-church.live.
 *
 * Run with: ddev drush php:script scripts/retire-d7-deleted.php
 *
 * A migration never deletes: when the office deletes a node in D7, the import
 * here simply stops seeing the row, and the node it made stays published. Find
 * them through the migrate maps — a map row whose source is gone from `legacy`:
 *
 *   SELECT m.sourceid1, m.destid1 FROM db.migrate_map_<migration> m
 *   LEFT JOIN legacy.node l ON l.nid = m.sourceid1
 *   WHERE l.nid IS NULL AND m.destid1 IS NOT NULL
 *
 * Whether to follow D7 is decided per node, so each one is declared below with
 * the node its old URL should land on. Add a row when a sync turns up another.
 *
 * Idempotent, and it aborts without changing anything unless every node is what
 * it expects: the right bundle and title, the import of the D7 nid named here,
 * and a published survivor. On an environment whose database predates the
 * 2026-10-24 sync, 1673 does not exist and nothing is done.
 */

use Drupal\pathauto\PathautoState;

// mcc_retire_stale_aliases(), shared with the other scripts that retire URLs.
require_once __DIR__ . '/ia-page-slugs.inc.php';

// nid => [migration, D7 nid, bundle, title, redirect-to nid, why].
const RETIRE = [
  // The office folded the youth services into the adult ones on 2026-10-06 and
  // deleted the separate entries. Here 1325 and 1553 keep the tip's titles,
  // which already say "& Youth", so the youth-only entries only repeated them.
  1500 => ['mcc_calendar_event', 1500, 'calendar_event', 'Youth Church Services', 1325, 'deleted in D7 after the youth service was folded into 1325'],
  1554 => ['mcc_calendar_event', 1554, 'calendar_event', 'Youth Sunday School', 1553, 'deleted in D7 after youth Sunday School was folded into 1553'],
  // A homepage teaser; it was one of the five front-page cards.
  1673 => ['mcc_announcement', 1156, 'announcement', 'Highway 52 Youth Group', 8, 'its D7 homepage teaser was deleted'],
];

$storage = \Drupal::entityTypeManager()->getStorage('node');
$database = \Drupal::database();

// Check everything before touching anything.
$nodes = [];
foreach (RETIRE as $nid => [$migration, $d7_nid, $bundle, $title, $redirect_to]) {
  $node = $storage->load($nid);
  if (!$node) {
    print "Aborting: node $nid does not exist here. Has this environment received the 2026-10-24 sync?\n";
    exit(1);
  }
  if ($node->bundle() !== $bundle || trim($node->label()) !== $title) {
    printf("Aborting: node %d is a %s titled \"%s\", not a %s titled \"%s\". Repurposed?\n",
      $nid, $node->bundle(), $node->label(), $bundle, $title);
    exit(1);
  }
  $table = 'migrate_map_' . $migration;
  $source = $database->schema()->tableExists($table)
    ? $database->select($table, 'm')->fields('m', ['sourceid1'])->condition('destid1', $nid)->execute()->fetchField()
    : FALSE;
  if ((int) $source !== $d7_nid) {
    printf("Aborting: node %d is not the %s import of D7 nid %d.\n", $nid, $migration, $d7_nid);
    exit(1);
  }
  $survivor = $storage->load($redirect_to);
  if (!$survivor || !$survivor->isPublished()) {
    printf("Aborting: node %d, where %d's URL should land, is missing or unpublished.\n", $redirect_to, $nid);
    exit(1);
  }
  $nodes[$nid] = $node;
}

foreach ($nodes as $nid => $node) {
  [, $d7_nid, , $title, $redirect_to, $why] = RETIRE[$nid];
  if ($node->isPublished()) {
    // Unpublish first. Pathauto regenerates an alias on every save, so the alias
    // has to be retired *after* this save or it gets handed straight back.
    $node->setUnpublished();
    $node->set('path', ['alias' => '', 'pathauto' => PathautoState::SKIP]);
    $node->setNewRevision(TRUE);
    $node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
    $node->setRevisionLogMessage(sprintf(
      'Unpublished: %s (D7 nid %d). Old URL 301s to node %d (scripts/retire-d7-deleted.php).',
      $why, $d7_nid, $redirect_to
    ));
    $node->save();
    printf("  %d \"%s\" unpublished.\n", $nid, $title);
  }
  else {
    printf("  %d already unpublished.\n", $nid);
  }

  // No alias is kept, so every alias the node has is retired.
  mcc_retire_stale_aliases($nid, '', $redirect_to);

  $remaining = $database->select('path_alias', 'a')
    ->condition('path', '/node/' . $nid)
    ->countQuery()->execute()->fetchField();
  printf("  aliases left on %d: %d\n", $nid, $remaining);
}
