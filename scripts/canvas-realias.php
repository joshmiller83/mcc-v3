<?php

/**
 * @file
 * Repoints a Canvas page to a new URL alias and 301s the old one.
 *
 * Run with: ddev drush php:script scripts/canvas-realias.php -- <id> <new-alias>
 *
 * Why this exists rather than just setting the `path` field:
 *
 * Setting `path` on a canvas_page *inserts* a second path_alias row instead of
 * updating the existing one, so the old alias stays live alongside the new one.
 * That matters twice over. `redirect.auto_redirect` hooks
 * redirect_path_alias_update(), which never fires on an insert, so no 301 is
 * created — and a live alias silently shadows a redirect anyway, because
 * redirects are matched only after inbound path processing has already resolved
 * the alias to /page/N. Nodes don't behave this way; pathauto updates their
 * alias in place and the 301 appears on its own.
 *
 * So the old alias has to be deleted first, and the redirect written by hand.
 */

use Drupal\redirect\Entity\Redirect;

[$id, $new_alias] = array_slice($extra ?? [], 0, 2) + [NULL, NULL];

if (!$id || !$new_alias) {
  print "usage: canvas-realias.php -- <canvas_page_id> <new-alias>\n";
  return;
}
if (!str_starts_with($new_alias, '/')) {
  print "alias must start with a slash\n";
  return;
}

$page = \Drupal::entityTypeManager()->getStorage('canvas_page')->load($id);
if (!$page) {
  print "no canvas_page with id $id\n";
  return;
}

$system_path = '/page/' . $page->id();
$alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');

// Every alias currently pointing at this page, so we can retire the stale ones.
$existing = $alias_storage->loadByProperties(['path' => $system_path]);

$page->set('path', ['alias' => $new_alias]);
$page->save();

foreach ($existing as $alias_entity) {
  $old = $alias_entity->getAlias();
  if ($old === $new_alias) {
    continue;
  }

  // Delete before redirecting: a live alias wins over a redirect.
  $alias_entity->delete();

  $source = ltrim($old, '/');
  $already = \Drupal::entityTypeManager()->getStorage('redirect')
    ->loadByProperties(['redirect_source__path' => $source]);
  if ($already) {
    print "  $old -> redirect already exists\n";
    continue;
  }

  // Point at the system path, not the new alias: Drupal resolves the canonical
  // alias at request time, so this keeps working through future slug changes.
  Redirect::create([
    'redirect_source' => ['path' => $source, 'query' => []],
    'redirect_redirect' => ['uri' => 'internal:' . $system_path],
    'status_code' => 301,
    'language' => 'und',
  ])->save();

  print "  301: $old -> $system_path\n";
}

print "canvas_page $id ({$page->label()}) is now at $new_alias\n";
