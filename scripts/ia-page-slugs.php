<?php

/**
 * @file
 * Applies the new IA slugs to the migrated D7 utility pages, and retires the
 * ones the new structure supersedes.
 *
 * Run with: ddev drush php:script scripts/ia-page-slugs.php
 *
 * Idempotent — safe to re-run after a migration re-import, which is the point:
 * `mcc_page` sets aliases from the `page_content` pathauto pattern
 * (/[node:title]), so a re-import flattens every slug set here. Running this
 * again puts the IA back.
 *
 * Each alias is pinned with PathautoState::SKIP so that an ordinary editor save
 * doesn't quietly revert it to /[node:title].
 */

use Drupal\pathauto\PathautoState;
use Drupal\redirect\Entity\Redirect;

/**
 * Retires every alias for a node except the one it should now have, 301ing each.
 *
 * Setting the `path` field *inserts* a new path_alias row rather than updating
 * the existing one, so the old alias stays live alongside the new one. That
 * matters twice: redirect.auto_redirect hooks redirect_path_alias_update(),
 * which never fires on an insert, and a live alias shadows a redirect anyway,
 * because redirects are matched only after inbound path processing has already
 * resolved the alias to /node/N.
 *
 * The redirects created here are ordinary redirect-module entities, editable by
 * an admin at /admin/config/search/redirect like any hand-made one.
 */
function mcc_retire_stale_aliases(int $nid, string $keep): void {
  $system_path = '/node/' . $nid;
  $redirect_storage = \Drupal::entityTypeManager()->getStorage('redirect');

  foreach (\Drupal::entityTypeManager()->getStorage('path_alias')
    ->loadByProperties(['path' => $system_path]) as $alias_entity) {

    $old = $alias_entity->getAlias();
    if ($old === $keep) {
      continue;
    }

    // Delete before redirecting: a live alias wins over a redirect.
    $alias_entity->delete();

    $source = ltrim($old, '/');
    if ($redirect_storage->loadByProperties(['redirect_source__path' => $source])) {
      continue;
    }
    // Point at /node/N, not the new alias, so this survives future slug changes.
    Redirect::create([
      'redirect_source' => ['path' => $source, 'query' => []],
      'redirect_redirect' => ['uri' => 'internal:' . $system_path],
      'status_code' => 301,
      'language' => 'und',
    ])->save();
    print "        301: $old -> $system_path\n";
  }
}

/**
 * D7 nid => IA slug, for pages the new structure keeps.
 */
const SLUGS = [
  // About
  3    => '/about/who-we-are',
  17   => '/about/beliefs',
  18   => '/about/beliefs/salvation',
  19   => '/about/beliefs/baptism',
  20   => '/about/beliefs/the-church',
  21   => '/about/beliefs/christ',
  22   => '/about/beliefs/the-bible',
  101  => '/about/history',

  // Sunday mornings — these belong to the Worship Service ministry, which is
  // how D7's own sidebar had them.
  13   => '/ministries/worship-service/sunday-school',
  12   => '/ministries/worship-service/nursery',
  10   => '/ministries/worship-service/kids-worship',

  // Ministries that were modelled as pages in D7.
  1098 => '/ministries/prayer-outreach',
  1100 => '/ministries/emergency-response',
];

/**
 * D7 nid => why it is retired.
 *
 * These are also skipped in mcc_page.yml, so a re-import will not bring them
 * back. Their D7 content remains in the `legacy` database if ever needed.
 */
const RETIRED = [
  1   => 'D7 body was "Hello World."; the real front page is Canvas page 9',
  2   => 'content moved to the /im-new Canvas page',
  14  => 'NULL body in D7 — a placeholder never filled in',
  15  => 'NULL body in D7 — a placeholder never filled in',
  16  => 'NULL body in D7 — a placeholder never filled in',
  29  => 'superseded by the /ministries Canvas landing page',
  41  => 'superseded by ministry node 40 at /ministries/missions',
  189 => 'a page node duplicating bio content',
  344 => 'test fixture',
];

$storage = \Drupal::entityTypeManager()->getStorage('node');
$alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');

print "Applying IA slugs:\n";
foreach (SLUGS as $nid => $slug) {
  $node = $storage->load($nid);
  if (!$node) {
    print "  nid $nid: not found, skipping\n";
    continue;
  }
  $current = \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $nid);
  if ($current === $slug) {
    printf("  %-4s %-34s already %s\n", $nid, substr($node->label(), 0, 34), $slug);
  }
  else {
    $node->set('path', ['alias' => $slug, 'pathauto' => PathautoState::SKIP]);
    $node->save();
    printf("  %-4s %-34s %s -> %s\n", $nid, substr($node->label(), 0, 34), $current, $slug);
  }
  // Always sweep: getAliasByPath() returns the newest alias, so a node can look
  // settled while a stale alias is still live and shadowing its redirect.
  mcc_retire_stale_aliases($nid, $slug);
}

print "\nRetiring superseded pages:\n";
foreach (RETIRED as $nid => $why) {
  $node = $storage->load($nid);
  if (!$node) {
    printf("  %-4s already gone (%s)\n", $nid, $why);
    continue;
  }
  $label = $node->label();

  // Unpublish first. Pathauto regenerates an alias on every save, so the alias
  // has to be retired *after* this save or it gets handed straight back — and a
  // live alias silently shadows a redirect, because redirects are matched only
  // after inbound path processing has resolved the alias.
  $node->setUnpublished();
  $node->set('path', ['alias' => '', 'pathauto' => PathautoState::SKIP]);
  $node->save();

  foreach ($alias_storage->loadByProperties(['path' => '/node/' . $nid]) as $alias) {
    $alias->delete();
  }

  printf("  %-4s %-34s unpublished — %s\n", $nid, substr($label, 0, 34), $why);
}
