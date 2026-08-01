<?php

/**
 * @file
 * The alias-retirement helper, shared by the scripts that move URLs around.
 *
 * Kept apart from ia-page-slugs.php so a script can use the helper without also
 * running that script's slug and retirement passes. Include it with
 * `require_once __DIR__ . '/ia-page-slugs.inc.php';` — it defines a function and
 * does nothing else.
 */

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
