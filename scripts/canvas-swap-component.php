<?php

/**
 * @file
 * Swaps a component in a Canvas page's tree for a different one.
 *
 * Run with:
 *   ddev drush php:script scripts/canvas-swap-component.php -- <page_id> <target_component_id> <new_component_id>
 *
 * The component tree on a canvas_page is a flat list of items; hierarchy comes
 * from each item's parent_uuid + slot. Swapping therefore means: find the target
 * item, drop it and everything descended from it, and append a new item in the
 * same parent/slot position.
 *
 * Used to replace CareSphere demo card grids with the real Views blocks that
 * list our own content.
 */

[$page_id, $target_id, $new_id] = array_slice($extra ?? [], 0, 3) + [NULL, NULL, NULL];

if (!$page_id || !$target_id || !$new_id) {
  print "usage: canvas-swap-component.php -- <page_id> <target_component_id> <new_component_id>\n";
  return;
}

$page = \Drupal::entityTypeManager()->getStorage('canvas_page')->load($page_id);
if (!$page) {
  print "no canvas_page with id $page_id\n";
  return;
}

$component = \Drupal::entityTypeManager()->getStorage('component')->load($new_id);
if (!$component) {
  print "no component '$new_id' — is the block plugin discovered?\n";
  return;
}
// getVersions() returns a list of version hashes, not a keyed map — take the
// active one rather than trying to key into it.
$version = $component->getActiveVersion();

// Block components carry their plugin settings as inputs. Seed them from the
// component's own defaults so the block renders with its configured label
// behaviour rather than an empty settings array.
$defaults = $component->getSettings()['default_settings'] ?? [];
unset($defaults['id'], $defaults['provider']);
$inputs = $defaults ? json_encode($defaults) : '{}';

$items = [];
foreach ($page->get('components') as $item) {
  $items[] = $item->getValue();
}

// Locate the component being replaced.
$target = NULL;
foreach ($items as $item) {
  if ($item['component_id'] === $target_id) {
    $target = $item;
    break;
  }
}
if (!$target) {
  print "no component '$target_id' on this page\n";
  return;
}

// Everything under the target goes with it.
$doomed = [$target['uuid'] => TRUE];
do {
  $grew = FALSE;
  foreach ($items as $item) {
    if (isset($doomed[$item['parent_uuid']]) && !isset($doomed[$item['uuid']])) {
      $doomed[$item['uuid']] = TRUE;
      $grew = TRUE;
    }
  }
} while ($grew);

$replacement = [
  'parent_uuid' => $target['parent_uuid'],
  'slot' => $target['slot'],
  'uuid' => \Drupal::service('uuid')->generate(),
  'component_id' => $new_id,
  'component_version' => $version,
  'inputs' => $inputs,
  'label' => NULL,
];

$kept = [];
$inserted = FALSE;
foreach ($items as $item) {
  if (isset($doomed[$item['uuid']])) {
    // Drop the target itself, but put the replacement in its position. A
    // root-level target has parent_uuid NULL, which no item's uuid ever
    // matches — anchoring on the target's own position instead of its parent
    // handles that case as well as nested ones.
    if ($item['uuid'] === $target['uuid']) {
      $kept[] = $replacement;
      $inserted = TRUE;
    }
    continue;
  }
  $kept[] = $item;
}
if (!$inserted) {
  $kept[] = $replacement;
}

$page->set('components', $kept);
$page->save();

printf("page %d: replaced %s (and %d descendant%s) with %s\n",
  $page_id, $target_id, count($doomed) - 1, count($doomed) === 2 ? '' : 's', $new_id);
