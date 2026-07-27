<?php

/**
 * @file
 * Seeds the Leadership Structure terms with the copy the bio pages read.
 *
 * The leadership listing and every bio detail page are driven entirely by these
 * terms — nothing about a group is hardcoded in Twig or CSS:
 *
 * - name               — the section heading on /who-we-are/our-leadership.
 * - description        — the role explainer. This is what keeps a bio page from
 *   looking empty: only three of twenty people have written a biography, so
 *   "What deacons do" is the paragraph most detail pages actually show.
 * - field_role_singular — one member of the group ("Elder" for "Elders"), used
 *   as the eyebrow above a person's name.
 * - field_feature_group — render this group as one wide card above the rest
 *   instead of a grid. Meant for a group of one, like the senior minister.
 *
 * Section order is the vocabulary's own term order, so reordering the groups at
 * /admin/structure/taxonomy/manage/mcc_leadership_structure/overview reorders
 * the page. A group with nobody in it is skipped automatically.
 *
 * The explainer copy below is drafted, not approved — it came from the design
 * handoff as placeholder. The church should read it and rewrite it in their own
 * words; that is an ordinary term edit, no developer needed.
 *
 * Safe to re-run, but it overwrites edits made in the UI. Run with:
 *   ddev drush php:script scripts/mcc_setup_leadership_groups.php
 */

$term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

// term name => [singular label, feature as a wide card?, explainer]
$groups = [
  'Senior Minister' => [
    'Senior Minister',
    TRUE,
    'Preaches on Sunday mornings, plans worship with the elders, and is available for pastoral care, hospital visits, weddings, and funerals.',
  ],
  'Elders' => [
    'Elder',
    FALSE,
    'Elders provide spiritual oversight for the congregation — prayer, counsel, and care for members walking through hard seasons. They meet monthly, and any member can bring a concern to them.',
  ],
  'Deacons' => [
    'Deacon',
    FALSE,
    'Deacons handle the practical needs of the church family — communion, benevolence, building care, and hands-on service. If something needs doing, a deacon is usually the one doing it.',
  ],
  'Trustees' => [
    'Trustee',
    FALSE,
    "Trustees steward the church's property and finances on behalf of the congregation, and sign for the church in legal matters.",
  ],
  'Ministry Leaders' => [
    'Ministry Leader',
    FALSE,
    'Ministry leaders run the ministries week to week — youth, education, missions, meals, music, and communications. They are the people to ask about getting involved.',
  ],
  // Youth has no bios attached to it, so it never renders a section. The
  // singular label is set anyway in case someone is added to it later.
  'Youth' => ['Youth Leader', FALSE, NULL],
];

foreach ($groups as $name => [$singular, $feature, $explainer]) {
  $terms = $term_storage->loadByProperties([
    'vid' => 'mcc_leadership_structure',
    'name' => $name,
  ]);
  if (!$terms) {
    print "SKIP: no '$name' term found\n";
    continue;
  }
  $term = reset($terms);

  $term->set('field_role_singular', $singular);
  $term->set('field_feature_group', $feature);
  // Leave an explainer someone has already written in the UI alone when this
  // script has nothing better to offer.
  if ($explainer !== NULL) {
    $term->set('description', [
      'value' => '<p>' . $explainer . '</p>',
      'format' => 'content_format',
    ]);
  }
  $term->save();

  print sprintf(
    "%-17s singular=%-16s %s\n",
    $name,
    $singular,
    $feature ? 'featured card' : ''
  );
}

print "Done.\n";
