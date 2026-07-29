<?php

/**
 * @file
 * Folds the duplicate "I'm New" page into Get Involved.
 *
 * Run with:
 *   ddev drush php:script scripts/ia-get-involved-merge.php
 *
 * Why this exists
 * ---------------
 * Canvas pages 3 ("Get involved", /get-involved) and 11 ("I'm New", /im-new)
 * shipped with the same content — same five bands, same three serve cards, same
 * four FAQ items, differing only in button label casing. Page 11's own H1 is
 * "Get Involved at MCC", so it was Get Involved content wearing the wrong name;
 * there was never an "I'm New" page in the sense of content for first-time
 * visitors.
 *
 * Page 11 is the copy that got the structural repair (see
 * get-involved-structure.php), so it is the one that survives. It takes over
 * /get-involved, page 3 is retired, and /im-new 301s to it.
 *
 * The nav drops from five items to four. "I'm New" also comes out of the
 * footer's Visit column: with /im-new pointing at Get Involved, that column
 * would otherwise carry a second differently-labelled link to the page the
 * Connect column already links to.
 *
 * Alias handling follows canvas-realias.php, for the reason documented there:
 * setting `path` on a canvas_page *inserts* a second alias rather than updating
 * the existing one, no 301 is minted because redirect.auto_redirect only hooks
 * the update path, and a live alias silently shadows any redirect written by
 * hand because redirects are matched only after inbound path processing has
 * already resolved the alias. So old aliases are deleted first, then the
 * redirect is written — pointed at /page/N, never at an alias, so it survives
 * future slug changes.
 *
 * Idempotent: safe to re-run after a migration re-import.
 */

use Drupal\redirect\Entity\Redirect;

const KEEP_PAGE = 11;
const RETIRE_PAGE = 3;
const TARGET_ALIAS = '/get-involved';
const KEEP_LABEL = 'Get Involved';

/**
 * Menu links to remove, as [menu_name, link uri].
 */
const DROP_LINKS = [
  ['main', 'internal:/im-new'],
  ['footer-organization', 'internal:/im-new'],
];

$page_storage = \Drupal::entityTypeManager()->getStorage('canvas_page');
$alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');
$redirect_storage = \Drupal::entityTypeManager()->getStorage('redirect');

$keep = $page_storage->load(KEEP_PAGE);
if (!$keep) {
  print "no canvas_page " . KEEP_PAGE . " — nothing to do\n";
  return;
}

// 1. Retire the duplicate first, so it is not still holding the alias when the
//    surviving page claims it. Unpublish and clear the path in one save, then
//    sweep the aliases: a save can hand an alias straight back.
print "Retiring the duplicate:\n";
$retire = $page_storage->load(RETIRE_PAGE);
if (!$retire) {
  print "  page " . RETIRE_PAGE . " already gone\n";
}
else {
  if ($retire->isPublished()) {
    $retire->setUnpublished();
  }
  $retire->set('path', ['alias' => '']);
  $retire->save();
  printf("  page %d \"%s\" unpublished\n", RETIRE_PAGE, $retire->label());

  foreach ($alias_storage->loadByProperties(['path' => '/page/' . RETIRE_PAGE]) as $alias) {
    printf("  dropped alias %s\n", $alias->getAlias());
    $alias->delete();
  }
}

// 2. Move the surviving page onto the freed alias and rename it to match.
print "\nMoving page " . KEEP_PAGE . " to " . TARGET_ALIAS . ":\n";
$old_aliases = [];
foreach ($alias_storage->loadByProperties(['path' => '/page/' . KEEP_PAGE]) as $alias) {
  $old_aliases[$alias->id()] = $alias->getAlias();
}

if ($keep->label() !== KEEP_LABEL) {
  printf("  renamed \"%s\" -> \"%s\"\n", $keep->label(), KEEP_LABEL);
  $keep->set('title', KEEP_LABEL);
}

if (!in_array(TARGET_ALIAS, $old_aliases, TRUE)) {
  $keep->set('path', ['alias' => TARGET_ALIAS]);
}
$keep->save();

foreach ($alias_storage->loadByProperties(['path' => '/page/' . KEEP_PAGE]) as $alias) {
  if ($alias->getAlias() === TARGET_ALIAS) {
    continue;
  }
  $old = $alias->getAlias();

  // Delete before redirecting: a live alias wins over a redirect.
  $alias->delete();
  printf("  dropped alias %s\n", $old);

  $source = ltrim($old, '/');
  if ($redirect_storage->loadByProperties(['redirect_source__path' => $source])) {
    printf("  301 %s -> already exists\n", $old);
    continue;
  }
  Redirect::create([
    'redirect_source' => ['path' => $source],
    'redirect_redirect' => ['uri' => 'internal:/page/' . KEEP_PAGE],
    'status_code' => 301,
    'language' => 'und',
  ])->save();
  printf("  301 %s -> /page/%d\n", $old, KEEP_PAGE);
}
printf("  page %d now at %s\n", KEEP_PAGE, TARGET_ALIAS);

// 3. Take "I'm New" out of the menus it appears in.
print "\nMenu links:\n";
$link_storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
foreach (DROP_LINKS as [$menu, $uri]) {
  $links = $link_storage->loadByProperties(['menu_name' => $menu, 'link__uri' => $uri]);
  if (!$links) {
    printf("  %-20s %-22s already removed\n", $menu, $uri);
    continue;
  }
  foreach ($links as $link) {
    printf("  %-20s removed \"%s\"\n", $menu, $link->label());
    $link->delete();
  }
}

\Drupal::service('cache_tags.invalidator')->invalidateTags(['canvas_page_list', 'menu_link_content_list']);
print "\ndone\n";
