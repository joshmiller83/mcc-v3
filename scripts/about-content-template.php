<?php

/**
 * @file
 * Points the `page` bundle's full-view content template at mcc-page-body.
 *
 * Run with:
 *   ddev drush php:script scripts/about-content-template.php
 *
 * Why this exists
 * ---------------
 * `canvas.content_template.node.page.full` is shared by every `page`-bundle
 * node — Kids Worship, Nursery, Sunday School, Ministries, the About
 * children, all 23 of them. It used to embed two `mercury` theme components
 * (a heading bound to the title, a text block bound to field_content). This
 * script replaces both with one component, `mcc_theme:mcc-page-body`, which
 * branches internally on the new `field_section_nav` boolean: off, it
 * reproduces the old plain heading+body output; on (only the About children
 * so far — see scripts/about-history-structure.php,
 * scripts/about-beliefs-merge.php, scripts/about-who-we-are.php), it renders
 * the level-3 title band and sticky sidebar layout.
 *
 * The breadcrumb block moves from a top-level sibling into mcc-page-body's
 * `breadcrumb` slot (so its markup lives inside the level-3 title band on
 * pages that have one). Two new blocks — SectionRelatedBlock and
 * SectionPrevNextBlock — are added in the `related` and `footer` slots; both
 * render nothing (empty array) when the current node isn't in a
 * SectionFamilies family, so they're inert on every non-About page.
 *
 * The entity-field expressions for `lede` and `section_nav` are built by
 * string substitution on the two known-good expressions this template
 * already carried (title/value and field_content/processed) rather than
 * hand-typing the control-character-delimited format, to guarantee
 * byte-for-byte correctness.
 *
 * Idempotent: the tree is declared in full and replaces whatever is stored.
 */

use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\ContentTemplate;

$template = ContentTemplate::load('node.page.full');
if (!$template) {
  print "no content template node.page.full\n";
  return;
}

// Pull the two known-good expressions off the template as it stands today,
// so the replacements are guaranteed byte-identical in their control
// characters.
$existing = [];
foreach ($template->get('component_tree') as $key => $item) {
  $existing[$item['uuid']] = $item;
}

$title_expr = NULL;
$body_expr = NULL;
$breadcrumb_value = NULL;
foreach ($existing as $item) {
  if ($item['component_id'] === 'sdc.mercury.heading') {
    $title_expr = $item['inputs']['heading_text']['expression'];
  }
  if ($item['component_id'] === 'sdc.mercury.text') {
    $body_expr = $item['inputs']['text']['expression'];
  }
  if ($item['component_id'] === 'block.system_breadcrumb_block') {
    $breadcrumb_value = $item;
  }
}
if (!$title_expr || !$body_expr || !$breadcrumb_value) {
  print "aborting — couldn't find the expected existing components (heading/text/breadcrumb) to derive expressions from\n";
  return;
}

$lede_expr = str_replace(['field_content', 'processed'], ['field_description', 'value'], $body_expr);
$section_nav_expr = str_replace(['field_content', 'processed'], ['field_section_nav', 'value'], $body_expr);

$component = fn(string $id) => Component::load($id);

$PAGE_BODY = '7c9a0a10-0000-4000-8000-000000000001';
$BREADCRUMB = $breadcrumb_value['uuid'];
$RELATED = '7c9a0a10-0000-4000-8000-000000000002';
$FOOTER = '7c9a0a10-0000-4000-8000-000000000003';

$page_body_component = $component('sdc.mcc_theme.mcc-page-body');
$related_component = $component('block.mcc_section_related');
$prevnext_component = $component('block.mcc_section_prevnext');
if (!$page_body_component || !$related_component || !$prevnext_component) {
  print "aborting — mcc-page-body / mcc_section_related / mcc_section_prevnext are not registered Canvas components. Run `ddev drush cache:rebuild` first.\n";
  return;
}

$tree = [
  [
    'uuid' => $PAGE_BODY,
    'parent_uuid' => NULL,
    'slot' => NULL,
    'component_id' => 'sdc.mcc_theme.mcc-page-body',
    'component_version' => $page_body_component->getActiveVersion(),
    'inputs' => [
      'title' => [
        'sourceType' => 'entity-field',
        'expression' => $title_expr,
      ],
      'lede' => [
        'sourceType' => 'entity-field',
        'expression' => $lede_expr,
      ],
      'body' => [
        'sourceType' => 'entity-field',
        'expression' => $body_expr,
      ],
      'section_nav' => [
        'sourceType' => 'entity-field',
        'expression' => $section_nav_expr,
      ],
    ],
  ],
  [
    'uuid' => $BREADCRUMB,
    'parent_uuid' => $PAGE_BODY,
    'slot' => 'breadcrumb',
    'component_id' => 'block.system_breadcrumb_block',
    'component_version' => $breadcrumb_value['component_version'],
    'inputs' => $breadcrumb_value['inputs'],
  ],
  [
    'uuid' => $RELATED,
    'parent_uuid' => $PAGE_BODY,
    'slot' => 'related',
    'component_id' => 'block.mcc_section_related',
    'component_version' => $related_component->getActiveVersion(),
    'inputs' => [
      'label' => 'Section related links',
      'label_display' => '0',
    ],
  ],
  [
    'uuid' => $FOOTER,
    'parent_uuid' => $PAGE_BODY,
    'slot' => 'footer',
    'component_id' => 'block.mcc_section_prevnext',
    'component_version' => $prevnext_component->getActiveVersion(),
    'inputs' => [
      'label' => 'Section prev/next',
      'label_display' => '0',
    ],
  ],
];

$template->set('component_tree', $tree);
$template->save();

printf("updated canvas.content_template.node.page.full: %d components\n", count($tree));
