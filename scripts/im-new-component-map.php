<?php

/**
 * @file
 * Emits JSON metadata for the Canvas page behind an alias.
 *
 * Usage:
 *   ddev drush php:script scripts/im-new-component-map.php -- /get-involved
 */

// drush php:script puts post-`--` arguments in $extra, not $argv — $argv[1] is
// the script path. This read the default on every run; it only looked like it
// worked because the default was the page being audited.
[$alias] = array_slice($extra ?? [], 0, 1) + ['/get-involved'];
$alias_manager = \Drupal::service('path_alias.manager');
// '/' is not an alias — it is whatever system.site points at. Resolve it first
// so the front page can be audited by the URL a visitor actually types.
$lookup = $alias === '/'
  ? \Drupal::config('system.site')->get('page.front')
  : $alias;
$path = $alias_manager->getPathByAlias($lookup);

if (!preg_match('#^/page/(\d+)$#', $path, $matches)) {
  fwrite(STDERR, "Alias $alias does not resolve to /page/N (got $path).\n");
  exit(1);
}

$page_id = (int) $matches[1];
$storage = \Drupal::entityTypeManager()->getStorage('canvas_page');
$page = $storage->load($page_id);
if (!$page) {
  fwrite(STDERR, "No canvas_page found for ID $page_id.\n");
  exit(1);
}

$summary_keys = [
  'tagline',
  'heading',
  'description',
  'label',
  'href',
  'question',
  'text',
  'cta_label',
  'cta_url',
];

$components = [];
$position = 1;
foreach ($page->get('components') as $item) {
  $value = $item->getValue();
  $inputs = json_decode($value['inputs'] ?? '{}', TRUE) ?: [];
  $summary = [];

  foreach ($summary_keys as $key) {
    if (!array_key_exists($key, $inputs)) {
      continue;
    }
    $raw = $inputs[$key];
    if (is_array($raw)) {
      if (isset($raw['value']) && is_string($raw['value'])) {
        $summary[$key] = trim(strip_tags($raw['value']));
      }
      continue;
    }
    if (is_string($raw)) {
      $summary[$key] = trim(strip_tags($raw));
    }
  }

  $components[] = [
    'position' => $position++,
    'uuid' => $value['uuid'] ?? '',
    'component_id' => $value['component_id'] ?? '',
    // Without the parent/slot pair a flat tree and a nested one serialise
    // identically here, which is the failure mode recorded in AGENTS.md.
    'parent_uuid' => $value['parent_uuid'] ?? NULL,
    'slot' => $value['slot'] ?? NULL,
    'input_keys' => array_keys($inputs),
    'summary' => $summary,
  ];
}

$payload = [
  'alias' => $alias,
  'resolved_path' => $path,
  'canvas_page_id' => $page_id,
  'canvas_page_label' => $page->label(),
  'component_count' => count($components),
  'components' => $components,
];

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
